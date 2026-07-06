<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Tool;

use DateTimeImmutable;
use Kytarna\Dto\DateInput;
use Kytarna\Mcp\Dto\McpPracticeSummaryDto;
use Kytarna\Mcp\Dto\McpProgressEntryDto;
use Kytarna\Mcp\Dto\McpProgressEntryListDto;
use Kytarna\Mcp\McpUserContextInterface;
use Kytarna\Model\Entity\Course;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\ProgressEntry;
use Kytarna\Service\Provider\CourseProviderInterface;
use Kytarna\Service\Provider\LectureProviderInterface;
use Kytarna\Service\Provider\ProgressProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Mcp\Capability\Attribute\McpTool;
use RuntimeException;

final readonly class ProgressTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private LectureProviderInterface $lectureProvider,
		private CourseProviderInterface $courseProvider,
		private ProgressProviderInterface $progressProvider,
		private WorkspaceProviderInterface $workspaceProvider,
	) {
	}

	/**
	 * Record a practice session for a lecture.
	 *
	 * @param int $lectureId Lecture ID
	 * @param string|null $practicedAt Date practised (YYYY-MM-DD); defaults to today
	 * @param string|null $note Optional markdown note about the session
	 * @param int|null $tempoBpm Optional tempo reached, in BPM
	 * @param int|null $durationMinutes Optional session length in minutes
	 */
	#[McpTool(name: 'create_progress_entry', description: 'Record a practice session for a lecture.')]
	public function createProgressEntry(
		int $lectureId,
		?string $practicedAt = null,
		?string $note = null,
		?int $tempoBpm = null,
		?int $durationMinutes = null,
	): McpProgressEntryDto {
		$user = $this->userContext->getUser();
		$lecture = $this->requireLecture($lectureId);

		$entry = $this->progressProvider->createEntry(
			$user,
			$lecture,
			DateInput::parse($practicedAt, 'practicedAt') ?? new DateTimeImmutable('today'),
			$note,
			$tempoBpm,
			$durationMinutes,
		);

		return McpProgressEntryDto::fromEntity($entry);
	}

	/**
	 * List practice entries for a lecture, oldest first. Optionally restrict to a date range.
	 *
	 * @param int $lectureId Lecture ID
	 * @param string|null $from Inclusive start date (YYYY-MM-DD)
	 * @param string|null $to Inclusive end date (YYYY-MM-DD)
	 */
	#[McpTool(name: 'list_progress_entries', description: 'List a lecture\'s practice entries, optionally within a date range.')]
	public function listProgressEntries(int $lectureId, ?string $from = null, ?string $to = null): McpProgressEntryListDto
	{
		$user = $this->userContext->getUser();
		$lecture = $this->requireLecture($lectureId);
		$entries = array_map(
			static fn (ProgressEntry $entry): McpProgressEntryDto => McpProgressEntryDto::fromEntity($entry),
			$this->progressProvider->getEntriesByLecture($user, $lecture, $this->normalizeDate($from), $this->normalizeDate($to)),
		);

		return new McpProgressEntryListDto($entries);
	}

	/**
	 * Update a practice entry. Omitted parameters are left unchanged; pass an empty note to clear it.
	 *
	 * @param int $progressEntryId Progress entry ID
	 * @param string|null $practicedAt New date (YYYY-MM-DD)
	 * @param string|null $note New note; empty string clears it
	 * @param int|null $tempoBpm New tempo in BPM
	 * @param int|null $durationMinutes New duration in minutes
	 */
	#[McpTool(name: 'update_progress_entry', description: 'Update a practice entry (omitted fields unchanged).')]
	public function updateProgressEntry(
		int $progressEntryId,
		?string $practicedAt = null,
		?string $note = null,
		?int $tempoBpm = null,
		?int $durationMinutes = null,
	): McpProgressEntryDto {
		$user = $this->userContext->getUser();
		$entry = $this->requireEntry($progressEntryId);

		$updated = $this->progressProvider->updateEntry(
			$user,
			$entry,
			DateInput::parse($practicedAt, 'practicedAt') ?? $entry->practicedAt,
			$note === null ? $entry->note : ($note === '' ? null : $note),
			$tempoBpm ?? $entry->tempoBpm,
			$durationMinutes ?? $entry->durationMinutes,
		);

		return McpProgressEntryDto::fromEntity($updated);
	}

	/**
	 * Delete a practice entry.
	 *
	 * @param int $progressEntryId Progress entry ID
	 */
	#[McpTool(name: 'delete_progress_entry', description: 'Delete a practice entry (irreversible).')]
	public function deleteProgressEntry(int $progressEntryId): string
	{
		$user = $this->userContext->getUser();
		$entry = $this->requireEntry($progressEntryId);
		$this->progressProvider->deleteEntry($user, $entry);
		return 'Progress entry deleted.';
	}

	/**
	 * Aggregate practice stats for a single lecture or a whole course: total entries, total minutes,
	 * entries per ISO week, and the BPM trend (chronological {practicedAt, tempoBpm}). Provide exactly
	 * one of lectureId or courseId.
	 *
	 * @param int|null $lectureId Lecture ID (summarise one lecture)
	 * @param int|null $courseId Course ID (summarise the whole course)
	 * @param string|null $from Inclusive start date (YYYY-MM-DD)
	 * @param string|null $to Inclusive end date (YYYY-MM-DD)
	 */
	#[McpTool(name: 'get_practice_summary', description: 'Practice stats (totals, per-week counts, BPM trend) for a lecture or a course.')]
	public function getPracticeSummary(
		?int $lectureId = null,
		?int $courseId = null,
		?string $from = null,
		?string $to = null,
	): McpPracticeSummaryDto {
		if (($lectureId === null) === ($courseId === null)) {
			throw new RuntimeException('Provide exactly one of lectureId or courseId.');
		}

		$fromDate = $this->normalizeDate($from);
		$toDate = $this->normalizeDate($to);
		$user = $this->userContext->getUser();

		$summary = $lectureId !== null
			? $this->progressProvider->summarizeLecture($user, $this->requireLecture($lectureId), $fromDate, $toDate)
			: $this->progressProvider->summarizeCourse($user, $this->requireCourse((int) $courseId), $fromDate, $toDate);

		return McpPracticeSummaryDto::fromSummary($summary);
	}

	private function normalizeDate(?string $value): ?string
	{
		return DateInput::parse($value, 'date')?->format('Y-m-d');
	}

	private function requireLecture(int $lectureId): Lecture
	{
		$lecture = $this->lectureProvider->getLecture($lectureId);
		if ($lecture === null || !$this->workspaceProvider->isMember($this->userContext->getUser(), $lecture->course->workspace)) {
			throw new RuntimeException(sprintf('Lecture %d not found.', $lectureId));
		}
		return $lecture;
	}

	private function requireCourse(int $courseId): Course
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->userContext->getUser());
		$course = $workspace !== null ? $this->courseProvider->getCourse($workspace, $courseId) : null;
		if ($course === null) {
			throw new RuntimeException(sprintf('Course %d not found.', $courseId));
		}
		return $course;
	}

	private function requireEntry(int $entryId): ProgressEntry
	{
		$entry = $this->progressProvider->getEntry($entryId);
		if ($entry === null || !$this->workspaceProvider->isMember($this->userContext->getUser(), $entry->lecture->course->workspace)) {
			throw new RuntimeException(sprintf('Progress entry %d not found.', $entryId));
		}
		return $entry;
	}
}
