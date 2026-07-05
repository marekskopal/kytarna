<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use RuntimeException;
use Ukolio\Model\Entity\Enum\EventTypeEnum;
use Ukolio\Model\Entity\Enum\TaskRelationTypeEnum;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskRelation;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\TaskRelationRepository;

final readonly class TaskRelationProvider implements TaskRelationProviderInterface
{
	public function __construct(private TaskRelationRepository $taskRelationRepository, private EventProviderInterface $eventProvider,)
	{
	}

	/** @return list<TaskRelation> */
	public function findOutgoing(Task $task): array
	{
		$result = [];
		foreach ($this->taskRelationRepository->findOutgoing($task->id) as $rel) {
			$result[] = $rel;
		}
		return $result;
	}

	/** @return list<TaskRelation> */
	public function findIncoming(Task $task): array
	{
		$result = [];
		foreach ($this->taskRelationRepository->findIncoming($task->id) as $rel) {
			$result[] = $rel;
		}
		return $result;
	}

	public function getRelation(int $relationId): ?TaskRelation
	{
		return $this->taskRelationRepository->findOneById($relationId);
	}

	public function createRelation(User $author, Task $source, Task $target, TaskRelationTypeEnum $type): TaskRelation
	{
		$this->assertNoSelfRelation($source, $target);
		$this->assertSameWorkspace($source, $target);
		$this->assertNoDuplicate($source, $target, $type);
		$this->assertNoCycle($source, $target, $type);

		$now = new DateTimeImmutable();
		$relation = new TaskRelation(sourceTask: $source, targetTask: $target, type: $type, createdBy: $author);
		$relation->createdAt = $now;
		$relation->updatedAt = $now;

		$this->taskRelationRepository->persist($relation);

		$this->eventProvider->recordEvent(
			$author,
			$source->project,
			EventTypeEnum::TaskRelationCreated,
			[
				'relationId' => $relation->id,
				'type' => $type->value,
				'targetTaskId' => $target->id,
				'targetTaskName' => $target->name,
			],
			$source->id,
		);

		return $relation;
	}

	public function deleteRelation(User $author, TaskRelation $relation): void
	{
		$source = $relation->sourceTask;
		$target = $relation->targetTask;
		$type = $relation->type;

		$this->taskRelationRepository->delete($relation);

		$this->eventProvider->recordEvent(
			$author,
			$source->project,
			EventTypeEnum::TaskRelationDeleted,
			[
				'relationId' => $relation->id,
				'type' => $type->value,
				'targetTaskId' => $target->id,
				'targetTaskName' => $target->name,
			],
			$source->id,
		);
	}

	public function deleteAllForTask(Task $task): void
	{
		foreach ($this->taskRelationRepository->findOutgoing($task->id) as $rel) {
			$this->taskRelationRepository->delete($rel);
		}
		foreach ($this->taskRelationRepository->findIncoming($task->id) as $rel) {
			$this->taskRelationRepository->delete($rel);
		}
	}

	private function assertNoSelfRelation(Task $source, Task $target): void
	{
		if ($source->id === $target->id) {
			throw new RuntimeException('A task cannot relate to itself.');
		}
	}

	private function assertSameWorkspace(Task $source, Task $target): void
	{
		if ($source->project->workspace->id !== $target->project->workspace->id) {
			throw new RuntimeException('Related tasks must belong to the same workspace.');
		}
	}

	private function assertNoDuplicate(Task $source, Task $target, TaskRelationTypeEnum $type): void
	{
		if ($this->taskRelationRepository->findPair($source->id, $target->id, $type) !== null) {
			throw new RuntimeException('This relation already exists.');
		}

		if ($type->isSymmetric()
			&& $this->taskRelationRepository->findPair($target->id, $source->id, $type) !== null
		) {
			throw new RuntimeException('This relation already exists.');
		}
	}

	private function assertNoCycle(Task $source, Task $target, TaskRelationTypeEnum $type): void
	{
		if ($type !== TaskRelationTypeEnum::Parent && $type !== TaskRelationTypeEnum::DependsOn) {
			return;
		}

		$sourceId = $source->id;
		$visited = [];
		$queue = [$target->id];

		while ($queue !== []) {
			$current = array_shift($queue);
			if ($current === $sourceId) {
				throw new RuntimeException(sprintf(
					'Adding this %s relation would create a cycle.',
					$type->value,
				));
			}
			if (isset($visited[$current])) {
				continue;
			}
			$visited[$current] = true;

			foreach ($this->taskRelationRepository->findOutgoingByType($current, $type) as $rel) {
				$queue[] = $rel->targetTask->id;
			}
		}
	}
}
