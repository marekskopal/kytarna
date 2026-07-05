<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Tool\Helper;

use RuntimeException;
use Ukolio\Model\Entity\Enum\StatusTypeEnum;
use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\Status;
use Ukolio\Service\Provider\StatusProviderInterface;
use Ukolio\Service\Provider\WorkflowProviderInterface;

final readonly class StatusResolver
{
	public function __construct(private StatusProviderInterface $statusProvider, private WorkflowProviderInterface $workflowProvider,)
	{
	}

	public function resolve(Project $project, ?int $statusId, ?string $statusName): ?Status
	{
		if ($statusId !== null) {
			$status = $this->statusProvider->getStatus($statusId);
			if ($status === null || $status->workflow->project->id !== $project->id) {
				throw new RuntimeException(sprintf('Status %d not found in project %d.', $statusId, $project->id));
			}
			return $status;
		}
		if ($statusName === null) {
			return null;
		}

		$workflow = $this->workflowProvider->getWorkflowByProject($project);
		if ($workflow === null) {
			throw new RuntimeException(sprintf('Workflow for project %d not found.', $project->id));
		}
		$needle = mb_strtolower($statusName);
		foreach ($this->statusProvider->getStatuses($workflow) as $status) {
			if (mb_strtolower($status->name) === $needle) {
				return $status;
			}
		}

		throw new RuntimeException(sprintf('Status "%s" not found in project %d.', $statusName, $project->id));
	}

	public function findByType(Project $project, StatusTypeEnum $type): ?Status
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
