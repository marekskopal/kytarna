<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Tool\Helper;

use Kytarna\Model\Entity\Course;
use Kytarna\Model\Entity\Enum\StatusTypeEnum;
use Kytarna\Model\Entity\Status;
use Kytarna\Service\Provider\StatusProviderInterface;
use Kytarna\Service\Provider\WorkflowProviderInterface;
use RuntimeException;

final readonly class StatusResolver
{
	public function __construct(private StatusProviderInterface $statusProvider, private WorkflowProviderInterface $workflowProvider,)
	{
	}

	public function resolve(Course $course, ?int $statusId, ?string $statusName): ?Status
	{
		if ($statusId !== null) {
			$status = $this->statusProvider->getStatus($statusId);
			if ($status === null || $status->workflow->course->id !== $course->id) {
				throw new RuntimeException(sprintf('Status %d not found in course %d.', $statusId, $course->id));
			}
			return $status;
		}
		if ($statusName === null) {
			return null;
		}

		$workflow = $this->workflowProvider->getWorkflowByCourse($course);
		if ($workflow === null) {
			throw new RuntimeException(sprintf('Workflow for course %d not found.', $course->id));
		}
		$needle = mb_strtolower($statusName);
		foreach ($this->statusProvider->getStatuses($workflow) as $status) {
			if (mb_strtolower($status->name) === $needle) {
				return $status;
			}
		}

		throw new RuntimeException(sprintf('Status "%s" not found in course %d.', $statusName, $course->id));
	}

	public function findByType(Course $course, StatusTypeEnum $type): ?Status
	{
		$workflow = $this->workflowProvider->getWorkflowByCourse($course);
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
