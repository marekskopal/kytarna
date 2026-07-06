<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use DateTimeImmutable;
use Iterator;
use Kytarna\Model\Entity\Course;
use Kytarna\Model\Entity\Enum\StatusTypeEnum;
use Kytarna\Model\Entity\Workflow;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Model\Repository\WorkflowRepository;

final readonly class WorkflowProvider implements WorkflowProviderInterface
{
	public function __construct(private WorkflowRepository $workflowRepository, private StatusProviderInterface $statusProvider,)
	{
	}

	public function getWorkflow(int $workflowId): ?Workflow
	{
		return $this->workflowRepository->findById($workflowId);
	}

	public function getWorkflowByCourse(Course $course): ?Workflow
	{
		return $this->workflowRepository->findByCourse($course->id);
	}

	/** @return Iterator<Workflow> */
	public function getWorkflowsInWorkspace(Workspace $workspace): Iterator
	{
		return $this->workflowRepository->findByWorkspace($workspace->id);
	}

	public function createDefaultWorkflow(Course $course): Workflow
	{
		$now = new DateTimeImmutable();
		$workflow = new Workflow(course: $course, name: 'Default');
		$workflow->createdAt = $now;
		$workflow->updatedAt = $now;

		$this->workflowRepository->persist($workflow);

		$this->statusProvider->createStatus($workflow, 'To Learn', '#94a3b8', StatusTypeEnum::Start, 0);
		$this->statusProvider->createStatus($workflow, 'Learning', '#fbbf24', StatusTypeEnum::Normal, 1);
		$this->statusProvider->createStatus($workflow, 'Mastered', '#4ade80', StatusTypeEnum::Finish, 2);

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
