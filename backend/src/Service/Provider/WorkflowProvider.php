<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use Iterator;
use Ukolio\Model\Entity\Enum\StatusTypeEnum;
use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\Workflow;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\WorkflowRepository;

final readonly class WorkflowProvider implements WorkflowProviderInterface
{
	public function __construct(private WorkflowRepository $workflowRepository, private StatusProviderInterface $statusProvider,)
	{
	}

	public function getWorkflow(int $workflowId): ?Workflow
	{
		return $this->workflowRepository->findById($workflowId);
	}

	public function getWorkflowByProject(Project $project): ?Workflow
	{
		return $this->workflowRepository->findByProject($project->id);
	}

	/** @return Iterator<Workflow> */
	public function getWorkflowsInWorkspace(Workspace $workspace): Iterator
	{
		return $this->workflowRepository->findByWorkspace($workspace->id);
	}

	public function createDefaultWorkflow(Project $project): Workflow
	{
		$now = new DateTimeImmutable();
		$workflow = new Workflow(project: $project, name: 'Default');
		$workflow->createdAt = $now;
		$workflow->updatedAt = $now;

		$this->workflowRepository->persist($workflow);

		$this->statusProvider->createStatus($workflow, 'To Do', '#94a3b8', StatusTypeEnum::Start, 0);
		$this->statusProvider->createStatus($workflow, 'In Progress', '#fbbf24', StatusTypeEnum::Normal, 1);
		$this->statusProvider->createStatus($workflow, 'Done', '#4ade80', StatusTypeEnum::Finish, 2);

		return $workflow;
	}

	public function updateWorkflow(Workflow $workflow, string $name): Workflow
	{
		$workflow->name = $name;
		$workflow->updatedAt = new DateTimeImmutable();
		$this->workflowRepository->persist($workflow);

		return $workflow;
	}
}
