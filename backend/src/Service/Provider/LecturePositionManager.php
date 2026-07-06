<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use DateTimeImmutable;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\Status;
use Kytarna\Model\Repository\LectureRepository;

/**
 * Encapsulates the per-status position bookkeeping for lectures.
 * Extracted from LectureProvider to keep that class focused on lifecycle / events.
 */
final readonly class LecturePositionManager
{
	public function __construct(private LectureRepository $lectureRepository)
	{
	}

	public function nextPosition(Status $status): int
	{
		$lectures = iterator_to_array($this->lectureRepository->findByStatus($status->id), false);
		if ($lectures === []) {
			return 0;
		}
		$max = 0;
		foreach ($lectures as $t) {
			if ($t->position > $max) {
				$max = $t->position;
			}
		}
		return $max + 1;
	}

	public function reorderWithinColumn(Lecture $lecture, int $newPosition): void
	{
		$oldPosition = $lecture->position;
		if ($oldPosition === $newPosition) {
			return;
		}
		$now = new DateTimeImmutable();
		foreach ($this->lectureRepository->findByStatus($lecture->status->id) as $sibling) {
			if ($sibling->id === $lecture->id) {
				continue;
			}
			$shifted = $this->shiftSiblingForReorder($sibling, $oldPosition, $newPosition);
			if (!$shifted) {
				continue;
			}

			$sibling->updatedAt = $now;
			$this->lectureRepository->persist($sibling);
		}
		$lecture->position = $newPosition;
	}

	public function closeGapInOldColumn(Lecture $lecture): void
	{
		$now = new DateTimeImmutable();
		foreach ($this->lectureRepository->findByStatus($lecture->status->id) as $sibling) {
			if ($sibling->id === $lecture->id || $sibling->position <= $lecture->position) {
				continue;
			}
			$sibling->position--;
			$sibling->updatedAt = $now;
			$this->lectureRepository->persist($sibling);
		}
	}

	public function openSlotInNewColumn(Status $newStatus, int $newPosition): void
	{
		$now = new DateTimeImmutable();
		foreach ($this->lectureRepository->findByStatus($newStatus->id) as $sibling) {
			if ($sibling->position < $newPosition) {
				continue;
			}
			$sibling->position++;
			$sibling->updatedAt = $now;
			$this->lectureRepository->persist($sibling);
		}
	}

	private function shiftSiblingForReorder(Lecture $sibling, int $oldPosition, int $newPosition): bool
	{
		if ($oldPosition < $newPosition) {
			if ($sibling->position > $oldPosition && $sibling->position <= $newPosition) {
				$sibling->position--;
				return true;
			}
			return false;
		}
		if ($sibling->position >= $newPosition && $sibling->position < $oldPosition) {
			$sibling->position++;
			return true;
		}
		return false;
	}
}
