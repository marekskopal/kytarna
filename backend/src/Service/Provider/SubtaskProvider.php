<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use DateTimeImmutable;
use RuntimeException;
use Kytario\Model\Entity\Enum\StatusTypeEnum;
use Kytario\Model\Entity\Enum\TaskRelationTypeEnum;
use Kytario\Model\Entity\Priority;
use Kytario\Model\Entity\Project;
use Kytario\Model\Entity\Status;
use Kytario\Model\Entity\Task;
use Kytario\Model\Entity\TaskRelation;
use Kytario\Model\Entity\User;
use Kytario\Model\Repository\TaskRelationRepository;

final readonly class SubtaskProvider implements SubtaskProviderInterface
{
	public function __construct(
		private TaskRelationRepository $taskRelationRepository,
		private TaskRelationProviderInterface $taskRelationProvider,
		private TaskProviderInterface $taskProvider,
		private WorkflowProviderInterface $workflowProvider,
		private StatusProviderInterface $statusProvider,
		private PriorityProviderInterface $priorityProvider,
	) {
	}

	/** @return list<TaskRelation> */
	public function getSubtaskRelations(Task $parent): array
	{
		$relations = [];
		foreach ($this->taskRelationRepository->findOutgoingByType($parent->id, TaskRelationTypeEnum::Parent) as $relation) {
			$relations[] = $relation;
		}

		return $relations;
	}

	/**
	 * @param list<int> $taskIds
	 * @return array<int, array{total: int, done: int}>
	 */
	public function getSubtaskCounts(array $taskIds): array
	{
		$counts = [];
		foreach ($this->taskRelationRepository->findByTypeAndSources(TaskRelationTypeEnum::Parent, $taskIds) as $relation) {
			$parentId = $relation->sourceTask->id;
			$counts[$parentId] ??= ['total' => 0, 'done' => 0];
			$counts[$parentId]['total']++;
			if ($relation->targetTask->status->type === StatusTypeEnum::Finish) {
				$counts[$parentId]['done']++;
			}
		}

		return $counts;
	}

	public function createSubtask(
		User $author,
		Task $parent,
		string $name,
		?string $description = null,
		?Priority $priority = null,
		?DateTimeImmutable $dueDate = null,
		?User $assignee = null,
	): TaskRelation {
		$status = $this->findStatusByType($parent->project, StatusTypeEnum::Start)
			?? throw new RuntimeException(sprintf('No Start status found for project %d.', $parent->project->id));

		$priority ??= $this->priorityProvider->getDefaultForWorkspace($parent->project->workspace)
			?? throw new RuntimeException('Workspace has no priorities configured.');

		$child = $this->taskProvider->createTask(
			author: $author,
			project: $parent->project,
			status: $status,
			name: $name,
			description: $description,
			priority: $priority,
			dueDate: $dueDate,
			assignee: $assignee ?? $author,
		);

		return $this->taskRelationProvider->createRelation($author, $parent, $child, TaskRelationTypeEnum::Parent);
	}

	/** @return array{startStatusId: ?int, finishStatusId: ?int} */
	public function getToggleStatusIds(Project $project): array
	{
		return [
			'startStatusId' => $this->findStatusByType($project, StatusTypeEnum::Start)?->id,
			'finishStatusId' => $this->findStatusByType($project, StatusTypeEnum::Finish)?->id,
		];
	}

	private function findStatusByType(Project $project, StatusTypeEnum $type): ?Status
	{
		$workflow = $this->workflowProvider->getWorkflowByProject($project);
		if ($workflow === null) {
			return null;
		}
		foreach ($this->statusProvider->getStatuses($workflow) as $status) {
			if ($status->type === $type) {
				return $status;
			}
		}

		return null;
	}
}
