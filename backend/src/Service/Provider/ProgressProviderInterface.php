<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use DateTimeImmutable;
use Kytarna\Model\Entity\Course;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\ProgressEntry;
use Kytarna\Model\Entity\User;
use Kytarna\Service\Provider\Dto\PracticeSummary;

interface ProgressProviderInterface
{
	public function getEntry(int $entryId): ?ProgressEntry;

	/** @return list<ProgressEntry> */
	public function getEntriesByLecture(User $user, Lecture $lecture, ?string $from = null, ?string $to = null): array;

	/** @return list<ProgressEntry> */
	public function getEntriesByCourse(User $user, Course $course, ?string $from = null, ?string $to = null): array;

	public function createEntry(
		User $author,
		Lecture $lecture,
		DateTimeImmutable $practicedAt,
		?string $note = null,
		?int $tempoBpm = null,
		?int $durationMinutes = null,
	): ProgressEntry;

	public function updateEntry(
		User $author,
		ProgressEntry $entry,
		DateTimeImmutable $practicedAt,
		?string $note,
		?int $tempoBpm,
		?int $durationMinutes,
	): ProgressEntry;

	public function deleteEntry(User $author, ProgressEntry $entry): void;

	public function summarizeLecture(User $user, Lecture $lecture, ?string $from = null, ?string $to = null): PracticeSummary;

	public function summarizeCourse(User $user, Course $course, ?string $from = null, ?string $to = null): PracticeSummary;
}
