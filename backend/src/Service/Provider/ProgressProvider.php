<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use DateTimeImmutable;
use Kytarna\Model\Entity\Course;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\ProgressEntry;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Repository\ProgressEntryRepository;
use Kytarna\Service\Provider\Dto\PracticeSummary;

final readonly class ProgressProvider implements ProgressProviderInterface
{
	public function __construct(private ProgressEntryRepository $progressEntryRepository)
	{
	}

	public function getEntry(int $entryId): ?ProgressEntry
	{
		return $this->progressEntryRepository->findById($entryId);
	}

	/** @return list<ProgressEntry> */
	public function getEntriesByLecture(User $user, Lecture $lecture, ?string $from = null, ?string $to = null): array
	{
		$result = [];
		foreach ($this->progressEntryRepository->findByLecture($lecture->id, $user->id, $from, $to) as $entry) {
			$result[] = $entry;
		}
		return $result;
	}

	/** @return list<ProgressEntry> */
	public function getEntriesByCourse(User $user, Course $course, ?string $from = null, ?string $to = null): array
	{
		$result = [];
		foreach ($this->progressEntryRepository->findByCourse($course->id, $user->id, $from, $to) as $entry) {
			$result[] = $entry;
		}
		return $result;
	}

	public function createEntry(
		User $author,
		Lecture $lecture,
		DateTimeImmutable $practicedAt,
		?string $note = null,
		?int $tempoBpm = null,
		?int $durationMinutes = null,
	): ProgressEntry {
		$now = new DateTimeImmutable();
		$entry = new ProgressEntry(
			lecture: $lecture,
			user: $author,
			practicedAt: $practicedAt,
			note: $note,
			tempoBpm: $tempoBpm,
			durationMinutes: $durationMinutes,
		);
		$entry->createdAt = $now;
		$entry->updatedAt = $now;

		$this->progressEntryRepository->persist($entry);

		return $entry;
	}

	public function updateEntry(
		User $author,
		ProgressEntry $entry,
		DateTimeImmutable $practicedAt,
		?string $note,
		?int $tempoBpm,
		?int $durationMinutes,
	): ProgressEntry {
		$entry->practicedAt = $practicedAt;
		$entry->note = $note;
		$entry->tempoBpm = $tempoBpm;
		$entry->durationMinutes = $durationMinutes;
		$entry->updatedAt = new DateTimeImmutable();

		$this->progressEntryRepository->persist($entry);

		return $entry;
	}

	public function deleteEntry(User $author, ProgressEntry $entry): void
	{
		$this->progressEntryRepository->delete($entry);
	}

	public function summarizeLecture(User $user, Lecture $lecture, ?string $from = null, ?string $to = null): PracticeSummary
	{
		return PracticeSummary::fromEntries($this->getEntriesByLecture($user, $lecture, $from, $to));
	}

	public function summarizeCourse(User $user, Course $course, ?string $from = null, ?string $to = null): PracticeSummary
	{
		return PracticeSummary::fromEntries($this->getEntriesByCourse($user, $course, $from, $to));
	}
}
