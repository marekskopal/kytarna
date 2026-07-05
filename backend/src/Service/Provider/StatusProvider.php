<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use Iterator;
use Ukolio\Model\Entity\Enum\StatusTypeEnum;
use Ukolio\Model\Entity\Status;
use Ukolio\Model\Entity\Workflow;
use Ukolio\Model\Repository\StatusRepository;

final readonly class StatusProvider implements StatusProviderInterface
{
	public function __construct(private StatusRepository $statusRepository)
	{
	}

	public function getStatus(int $statusId): ?Status
	{
		return $this->statusRepository->findById($statusId);
	}

	/** @return Iterator<Status> */
	public function getStatuses(Workflow $workflow): Iterator
	{
		return $this->statusRepository->findByWorkflow($workflow->id);
	}

	public function createStatus(Workflow $workflow, string $name, string $color, StatusTypeEnum $type, ?int $position = null): Status
	{
		if ($position === null) {
			$position = $this->nextPosition($workflow);
		} else {
			$this->shiftPositionsFrom($workflow, $position);
		}

		$now = new DateTimeImmutable();
		$status = new Status(workflow: $workflow, name: $name, color: $color, position: $position, type: $type);
		$status->createdAt = $now;
		$status->updatedAt = $now;

		$this->statusRepository->persist($status);

		return $status;
	}

	public function updateStatus(Status $status, string $name, string $color, StatusTypeEnum $type): Status
	{
		$status->name = $name;
		$status->color = $color;
		$status->type = $type;
		$status->updatedAt = new DateTimeImmutable();

		$this->statusRepository->persist($status);

		return $status;
	}

	public function moveStatus(Status $status, int $newPosition): Status
	{
		$oldPosition = $status->position;
		if ($oldPosition === $newPosition) {
			return $status;
		}

		$siblings = iterator_to_array($this->statusRepository->findByWorkflow($status->workflow->id), false);

		foreach ($siblings as $sibling) {
			if ($sibling->id === $status->id) {
				continue;
			}

			if ($oldPosition < $newPosition) {
				if ($sibling->position > $oldPosition && $sibling->position <= $newPosition) {
					$sibling->position--;
					$sibling->updatedAt = new DateTimeImmutable();
					$this->statusRepository->persist($sibling);
				}
			} else {
				if ($sibling->position >= $newPosition && $sibling->position < $oldPosition) {
					$sibling->position++;
					$sibling->updatedAt = new DateTimeImmutable();
					$this->statusRepository->persist($sibling);
				}
			}
		}

		$status->position = $newPosition;
		$status->updatedAt = new DateTimeImmutable();
		$this->statusRepository->persist($status);

		return $status;
	}

	public function deleteStatus(Status $status): void
	{
		$this->statusRepository->delete($status);
	}

	private function nextPosition(Workflow $workflow): int
	{
		$statuses = iterator_to_array($this->statusRepository->findByWorkflow($workflow->id), false);
		if ($statuses === []) {
			return 0;
		}
		$max = 0;
		foreach ($statuses as $s) {
			if ($s->position > $max) {
				$max = $s->position;
			}
		}
		return $max + 1;
	}

	private function shiftPositionsFrom(Workflow $workflow, int $fromPosition): void
	{
		foreach ($this->statusRepository->findByWorkflow($workflow->id) as $sibling) {
			if ($sibling->position >= $fromPosition) {
				$sibling->position++;
				$sibling->updatedAt = new DateTimeImmutable();
				$this->statusRepository->persist($sibling);
			}
		}
	}
}
