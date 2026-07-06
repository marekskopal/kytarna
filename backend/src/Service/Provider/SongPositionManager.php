<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use DateTimeImmutable;
use Kytarna\Model\Entity\Enum\LearningStatusEnum;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Repository\SongRepository;

/**
 * Per-(workspace, course, status) position bookkeeping for songs. A song's "column" is scoped by its
 * course (which may be null for a standalone library song) and status, mirroring LecturePositionManager.
 */
final readonly class SongPositionManager
{
	public function __construct(private SongRepository $songRepository)
	{
	}

	public function nextPosition(Song $song): int
	{
		return $this->nextPositionIn($song->workspace->id, $song->course?->id, $song->status);
	}

	public function nextPositionIn(int $workspaceId, ?int $courseId, LearningStatusEnum $status): int
	{
		$max = -1;
		foreach ($this->songRepository->findSiblings($workspaceId, $courseId, $status) as $sibling) {
			if ($sibling->position > $max) {
				$max = $sibling->position;
			}
		}
		return $max + 1;
	}

	public function reorderWithinColumn(Song $song, int $newPosition): void
	{
		$oldPosition = $song->position;
		if ($oldPosition === $newPosition) {
			return;
		}
		$now = new DateTimeImmutable();
		foreach ($this->songRepository->findSiblings($song->workspace->id, $song->course?->id, $song->status) as $sibling) {
			if ($sibling->id === $song->id) {
				continue;
			}
			if (!$this->shiftSiblingForReorder($sibling, $oldPosition, $newPosition)) {
				continue;
			}
			$sibling->updatedAt = $now;
			$this->songRepository->persist($sibling);
		}
		$song->position = $newPosition;
	}

	public function closeGapInColumn(Song $song): void
	{
		$now = new DateTimeImmutable();
		foreach ($this->songRepository->findSiblings($song->workspace->id, $song->course?->id, $song->status) as $sibling) {
			if ($sibling->id === $song->id || $sibling->position <= $song->position) {
				continue;
			}
			$sibling->position--;
			$sibling->updatedAt = $now;
			$this->songRepository->persist($sibling);
		}
	}

	public function openSlot(int $workspaceId, ?int $courseId, LearningStatusEnum $status, int $newPosition): void
	{
		$now = new DateTimeImmutable();
		foreach ($this->songRepository->findSiblings($workspaceId, $courseId, $status) as $sibling) {
			if ($sibling->position < $newPosition) {
				continue;
			}
			$sibling->position++;
			$sibling->updatedAt = $now;
			$this->songRepository->persist($sibling);
		}
	}

	private function shiftSiblingForReorder(Song $sibling, int $oldPosition, int $newPosition): bool
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
