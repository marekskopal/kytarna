<?php

declare(strict_types=1);

namespace Kytario\Mcp\Tool;

use DateTimeImmutable;
use Kytario\Dto\DateInput;
use Kytario\Mcp\Dto\McpLectureDto;
use Kytario\Mcp\Dto\McpLectureListDto;
use Kytario\Mcp\McpUserContextInterface;
use Kytario\Mcp\Tool\Helper\StatusResolver;
use Kytario\Model\Entity\Course;
use Kytario\Model\Entity\Enum\StatusTypeEnum;
use Kytario\Model\Entity\Lecture;
use Kytario\Model\Entity\Workspace;
use Kytario\Service\Provider\BulkLectureProviderInterface;
use Kytario\Service\Provider\CourseProviderInterface;
use Kytario\Service\Provider\Enum\BulkOpEnum;
use Kytario\Service\Provider\LectureCodeResolverInterface;
use Kytario\Service\Provider\LectureProviderInterface;
use Kytario\Service\Provider\LectureTagProviderInterface;
use Kytario\Service\Provider\StatusProviderInterface;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use Mcp\Capability\Attribute\McpTool;
use RuntimeException;

final readonly class LectureTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private CourseProviderInterface $courseProvider,
		private StatusProviderInterface $statusProvider,
		private LectureProviderInterface $lectureProvider,
		private LectureCodeResolverInterface $lectureCodeResolver,
		private WorkspaceProviderInterface $workspaceProvider,
		private LectureTagProviderInterface $lectureTagProvider,
		private StatusResolver $statusResolver,
		private BulkLectureProviderInterface $bulkLectureProvider,
	) {
	}

	/**
	 * List all lectures in a course, ordered by status then position. Optionally filter by status.
	 * Archived lectures are hidden by default; pass includeArchived=true to include them.
	 *
	 * @param int $courseId Course ID
	 * @param int|null $statusId Optional: only return lectures in this status
	 * @param bool $includeArchived Include archived lectures (default false)
	 */
	#[McpTool(
		name: 'list_lectures',
		description: 'List lectures in a course, optionally filtered by status. Hides archived lectures by default.',
	)]
	public function listLectures(int $courseId, ?int $statusId = null, bool $includeArchived = false): McpLectureListDto
	{
		$course = $this->requireCourse($courseId);

		$lectures = [];
		foreach ($this->lectureProvider->getLecturesByCourse($course, $includeArchived) as $lecture) {
			if ($statusId !== null && $lecture->status->id !== $statusId) {
				continue;
			}
			$lectures[] = McpLectureDto::fromEntity($lecture, $this->lectureTagProvider->getTagIdsForLecture($lecture));
		}

		return new McpLectureListDto($lectures);
	}

	/**
	 * Find a lecture by case-insensitive name match within a course. Returns the first match,
	 * preferring exact matches over substring matches.
	 *
	 * @param int $courseId Course ID
	 * @param string $name Lecture name to search for
	 */
	#[McpTool(
		name: 'find_lecture_by_name',
		description: 'Find a lecture in a course by name (case-insensitive). Prefers exact matches over substring matches.',
	)]
	public function findLectureByName(int $courseId, string $name): ?McpLectureDto
	{
		$course = $this->requireCourse($courseId);
		$needle = mb_strtolower($name);

		$exact = null;
		$partial = null;
		foreach ($this->lectureProvider->getLecturesByCourse($course) as $lecture) {
			$haystack = mb_strtolower($lecture->name);
			if ($haystack === $needle) {
				$exact = $lecture;
				break;
			}
			if ($partial === null && str_contains($haystack, $needle)) {
				$partial = $lecture;
			}
		}

		$found = $exact ?? $partial;

		return $found !== null
			? McpLectureDto::fromEntity($found, $this->lectureTagProvider->getTagIdsForLecture($found))
			: null;
	}

	/**
	 * Get a single lecture by numeric ID or by code (e.g. "MP-3").
	 *
	 * @param int|string $lectureId Lecture ID or code
	 */
	#[McpTool(name: 'get_lecture', description: 'Get a single lecture by numeric ID or code (e.g. "MP-3")')]
	public function getLecture(int|string $lectureId): McpLectureDto
	{
		$lecture = $this->requireLecture($lectureId);
		return McpLectureDto::fromEntity($lecture, $this->lectureTagProvider->getTagIdsForLecture($lecture));
	}

	/**
	 * Create a new lecture. By default it lands in the course's Start status (e.g. "To Learn").
	 * Provide statusId or statusName to put it in a different column.
	 *
	 * @param int $courseId Course ID
	 * @param string $name Lecture name
	 * @param string|null $description Optional markdown description
	 * @param int|null $statusId Optional explicit status ID
	 * @param string|null $statusName Optional status name (case-insensitive); ignored if statusId is given
	 * @param string|null $startDate Optional start date (YYYY-MM-DD). Used by the Timeline view.
	 * @param list<int>|null $tagIds Optional list of workspace tag IDs to apply to the new lecture
	 */
	#[McpTool(name: 'create_lecture', description: 'Create a lecture in a course. Lands in Start status by default.')]
	public function createLecture(
		int $courseId,
		string $name,
		?string $description = null,
		?int $statusId = null,
		?string $statusName = null,
		?string $startDate = null,
		?array $tagIds = null,
	): McpLectureDto {
		$user = $this->userContext->getUser();
		$course = $this->requireCourse($courseId);
		$status = $this->statusResolver->resolve($course, $statusId, $statusName)
			?? $this->statusResolver->findByType($course, StatusTypeEnum::Start)
			?? throw new RuntimeException(sprintf('No Start status found for course %d.', $courseId));

		$lecture = $this->lectureProvider->createLecture(
			author: $user,
			course: $course,
			status: $status,
			name: $name,
			description: $description,
			tagIds: $tagIds,
			startDate: DateInput::parse($startDate, 'startDate'),
		);

		return McpLectureDto::fromEntity($lecture, $this->lectureTagProvider->getTagIdsForLecture($lecture));
	}

	/**
	 * Update a lecture's editable fields. Omitted parameters are left unchanged.
	 *
	 * @param int|string $lectureId Lecture ID or code (e.g. "MP-3")
	 * @param string|null $name New name
	 * @param string|null $description New description
	 * @param string|null $startDate New start date (YYYY-MM-DD), or empty string to clear
	 * @param list<int>|null $tagIds Optional list of workspace tag IDs to apply (replaces the full set)
	 */
	#[McpTool(name: 'update_lecture', description: 'Update a lecture. Use move_lecture to change its status.')]
	public function updateLecture(
		int|string $lectureId,
		?string $name = null,
		?string $description = null,
		?string $startDate = null,
		?array $tagIds = null,
	): McpLectureDto {
		$user = $this->userContext->getUser();
		$lecture = $this->requireLecture($lectureId);

		$newStartDate = $this->resolveNewDate($lecture->startDate, $startDate);

		$updated = $this->lectureProvider->updateLecture(
			author: $user,
			lecture: $lecture,
			name: $name ?? $lecture->name,
			description: $description ?? $lecture->description,
			status: $lecture->status,
			tagIds: $tagIds,
			startDate: $newStartDate,
		);

		return McpLectureDto::fromEntity($updated, $this->lectureTagProvider->getTagIdsForLecture($updated));
	}

	/**
	 * Move a lecture to a different status (column). Provide either statusId or statusName.
	 * The lecture is appended to the end of the destination column.
	 *
	 * @param int|string $lectureId Lecture ID or code (e.g. "MP-3")
	 * @param int|null $statusId Target status ID
	 * @param string|null $statusName Target status name (case-insensitive); ignored if statusId is given
	 */
	#[McpTool(name: 'move_lecture', description: 'Move a lecture to a different status. Appends to the end of the destination column.')]
	public function moveLecture(int|string $lectureId, ?int $statusId = null, ?string $statusName = null): McpLectureDto
	{
		$user = $this->userContext->getUser();
		$lecture = $this->requireLecture($lectureId);
		$status = $this->statusResolver->resolve($lecture->course, $statusId, $statusName);
		if ($status === null) {
			throw new RuntimeException('Provide statusId or statusName to move the lecture.');
		}

		$position = $this->nextPositionInStatus($status->id);
		$moved = $this->lectureProvider->moveLecture($user, $lecture, $status, $position);

		return McpLectureDto::fromEntity($moved, $this->lectureTagProvider->getTagIdsForLecture($moved));
	}

	/**
	 * Archive a lecture. Archived lectures are hidden from boards and from the default lecture lists, but
	 * remain editable and can be unarchived. Records a LectureArchived event.
	 *
	 * @param int|string $lectureId Lecture ID or code (e.g. "MP-3")
	 */
	#[McpTool(name: 'archive_lecture', description: 'Archive a lecture (hides it from boards and default lists; reversible).')]
	public function archiveLecture(int|string $lectureId): McpLectureDto
	{
		$user = $this->userContext->getUser();
		$lecture = $this->requireLecture($lectureId);

		$archived = $this->lectureProvider->archiveLecture($user, $lecture);

		return McpLectureDto::fromEntity($archived, $this->lectureTagProvider->getTagIdsForLecture($archived));
	}

	/**
	 * Unarchive a previously archived lecture, restoring it to boards and default lists.
	 * Records a LectureUnarchived event.
	 *
	 * @param int|string $lectureId Lecture ID or code (e.g. "MP-3")
	 */
	#[McpTool(name: 'unarchive_lecture', description: 'Unarchive a lecture, restoring it to boards and default lists.')]
	public function unarchiveLecture(int|string $lectureId): McpLectureDto
	{
		$user = $this->userContext->getUser();
		$lecture = $this->requireLecture($lectureId);

		$unarchived = $this->lectureProvider->unarchiveLecture($user, $lecture);

		return McpLectureDto::fromEntity($unarchived, $this->lectureTagProvider->getTagIdsForLecture($unarchived));
	}

	/**
	 * Delete a lecture.
	 *
	 * @param int|string $lectureId Lecture ID or code (e.g. "MP-3")
	 */
	#[McpTool(name: 'delete_lecture', description: 'Delete a lecture (irreversible)')]
	public function deleteLecture(int|string $lectureId): string
	{
		$user = $this->userContext->getUser();
		$lecture = $this->requireLecture($lectureId);

		$this->lectureProvider->deleteLecture($user, $lecture);

		return 'Lecture deleted.';
	}

	/**
	 * Apply one operation to many lectures in the current workspace in a single batch.
	 * Per-lecture failures (not found, out of workspace, status mismatch) are returned as `skipped` —
	 * the call succeeds even if some ids could not be processed. Up to 200 ids per call.
	 *
	 * Operations and required `payload`:
	 * - "move": `{statusId: int}` — moves each lecture to the given status, appended to end of column
	 * - "tag": `{tagIds: int[]}` — adds these tag ids to each lecture's existing tags
	 * - "untag": `{tagIds: int[]}` — removes these tag ids from each lecture's existing tags
	 * - "delete": no payload — deletes each lecture
	 *
	 * @param list<int> $ids Lecture IDs (1-200). Order is preserved (matters for "move").
	 * @param string $op Operation name: move | tag | untag | delete
	 * @param array<string, mixed>|null $payload Per-op payload (see above).
	 * @return array{succeeded: list<int>, skipped: list<array{id: int, reason: string}>}
	 */
	#[McpTool(
		name: 'bulk_update_lectures',
		description: 'Apply one operation to many lectures (move|tag|untag|delete). Returns {succeeded, skipped}.',
	)]
	public function bulkUpdateLectures(array $ids, string $op, ?array $payload = null): array
	{
		$user = $this->userContext->getUser();
		$workspace = $this->requireWorkspace();

		$opEnum = BulkOpEnum::tryFrom($op);
		if ($opEnum === null) {
			throw new RuntimeException(sprintf('Unknown op "%s". Expected one of: move, tag, untag, delete.', $op));
		}

		return $this->bulkLectureProvider->execute($user, $workspace, $opEnum, $ids, $payload ?? []);
	}

	private function requireWorkspace(): Workspace
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->userContext->getUser());
		if ($workspace === null) {
			throw new RuntimeException('No active workspace.');
		}

		return $workspace;
	}

	private function requireCourse(int $courseId): Course
	{
		$workspace = $this->requireWorkspace();
		$course = $this->courseProvider->getCourse($workspace, $courseId);
		if ($course === null) {
			throw new RuntimeException(sprintf('Course %d not found.', $courseId));
		}

		return $course;
	}

	private function requireLecture(int|string $lectureId): Lecture
	{
		$lecture = $this->lectureCodeResolver->resolveForUser($this->userContext->getUser(), (string) $lectureId);
		if ($lecture === null) {
			throw new RuntimeException(sprintf('Lecture "%s" not found.', (string) $lectureId));
		}
		return $lecture;
	}

	/** Partial-update date semantics: null leaves the value unchanged, '' clears it, otherwise parse. */
	private function resolveNewDate(?DateTimeImmutable $current, ?string $value): ?DateTimeImmutable
	{
		if ($value === null) {
			return $current;
		}
		return DateInput::parse($value, 'date');
	}

	private function nextPositionInStatus(int $statusId): int
	{
		$status = $this->statusProvider->getStatus($statusId);
		if ($status === null) {
			throw new RuntimeException(sprintf('Status %d not found.', $statusId));
		}

		$max = -1;
		foreach ($this->lectureProvider->getLecturesByCourse($status->workflow->course) as $lecture) {
			if ($lecture->status->id === $statusId && $lecture->position > $max) {
				$max = $lecture->position;
			}
		}

		return $max + 1;
	}
}
