<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Iterator;
use Kytarna\Model\Entity\Enum\StatusTypeEnum;
use Kytarna\Model\Entity\Status;
use Kytarna\Model\Entity\Workflow;

interface StatusProviderInterface
{
	public function getStatus(int $statusId): ?Status;

	/** @return Iterator<Status> */
	public function getStatuses(Workflow $workflow): Iterator;

	public function createStatus(Workflow $workflow, string $name, string $color, StatusTypeEnum $type, ?int $position = null): Status;

	public function updateStatus(Status $status, string $name, string $color, StatusTypeEnum $type): Status;

	public function moveStatus(Status $status, int $position): Status;

	public function deleteStatus(Status $status): void;
}
