<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use DateTimeImmutable;
use Kytario\Model\Entity\Course;
use Kytario\Model\Entity\Lecture;
use Kytario\Model\Entity\ProgressEntry;
use Kytario\Model\Entity\User;
use Kytario\Service\Provider\Dto\PracticeSummary;

interface ProgressProviderInterface
{
	public function getEntry(int $entryId): ?ProgressEntry;

	/** @return list<ProgressEntry> */
	public function getEntriesByLecture(Lecture $lecture, ?string $from = null, ?string $to = null): array;

	/** @return list<ProgressEntry> */
	public function getEntriesByCourse(Course $course, ?string $from = null, ?string $to = null): array;

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

	public function summarizeLecture(Lecture $lecture, ?string $from = null, ?string $to = null): PracticeSummary;

	public function summarizeCourse(Course $course, ?string $from = null, ?string $to = null): PracticeSummary;
}
