<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Tool;

use Kytarna\Mcp\Dto\McpLectureDto;
use Kytarna\Mcp\Dto\McpLectureListDto;
use Kytarna\Mcp\McpUserContextInterface;
use Kytarna\Model\Entity\Course;
use Kytarna\Model\Entity\Enum\DifficultyEnum;
use Kytarna\Model\Entity\Enum\LearningStatusEnum;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Service\Auth\PermissionCheckerInterface;
use Kytarna\Service\Provider\BulkLectureProviderInterface;
use Kytarna\Service\Provider\CourseProviderInterface;
use Kytarna\Service\Provider\Enum\BulkOpEnum;
use Kytarna\Service\Provider\LectureCodeResolverInterface;
use Kytarna\Service\Provider\LectureProviderInterface;
use Kytarna\Service\Provider\LectureTagProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Mcp\Capability\Attribute\McpTool;
use RuntimeException;

final readonly class LectureTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private CourseProviderInterface $courseProvider,
		private LectureProviderInterface $lectureProvider,
		private LectureCodeResolverInterface $lectureCodeResolver,
		private WorkspaceProviderInterface $workspaceProvider,
		private LectureTagProviderInterface $lectureTagProvider,
		private BulkLectureProviderInterface $bulkLectureProvider,
		private PermissionCheckerInterface $permissionChecker,
	) {
	}

	/** Content management (create/edit/move/delete) is Teacher-only; students have read-only content. */
	private function requireTeacher(Workspace $workspace): void
	{
		if (!$this->permissionChecker->canManageLectures($this->userContext->getUser(), $workspace)) {
			throw new RuntimeException('Only the teacher (workspace owner) can manage lectures.');
		}
	}

	/**
	 * List all lectures in a course, ordered by status then position. Optionally filter by status or tuning.
	 * Archived lectures are hidden by default; pass includeArchived=true to include them.
	 *
	 * @param int $courseId Course ID
	 * @param string|null $status Optional: only return lectures in this status ("To Learn", "Learning" or "Mastered")
	 * @param string|null $tuning Optional: only return lectures whose tuning matches (case-insensitive substring,
	 *     e.g. "drop d")
	 * @param bool $includeArchived Include archived lectures (default false)
	 */
	#[McpTool(
		name: 'list_lectures',
		description: 'List lectures in a course, optionally filtered by status or tuning. Hides archived lectures by default.',
	)]
	public function listLectures(
		int $courseId,
		?string $status = null,
		?string $tuning = null,
		bool $includeArchived = false,
	): McpLectureListDto {
		$course = $this->requireCourse($courseId);
		$statusFilter = $status !== null && $status !== '' ? $this->parseStatus($status) : null;
		$tuningNeedle = $tuning !== null && $tuning !== '' ? mb_strtolower($tuning) : null;

		$lectures = [];
		foreach ($this->lectureProvider->getLecturesByCourse($course, $includeArchived) as $lecture) {
			if ($statusFilter !== null && $lecture->status !== $statusFilter) {
				continue;
			}
			if (
				$tuningNeedle !== null
				&& (
					$lecture->tuning === null
					|| !str_contains(mb_strtolower($lecture->tuning), $tuningNeedle)
				)
			) {
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
	 * Create a new lecture. By default it lands in the "To Learn" status.
	 * Provide status to put it in a different column.
	 *
	 * @param int $courseId Course ID
	 * @param string $name Lecture name
	 * @param string|null $description Optional markdown description
	 * @param string|null $status Optional status: "To Learn" (default), "Learning" or "Mastered"
	 * @param string|null $tuning Optional tuning, e.g. "E A D G B E" or "Drop D"
	 * @param int|null $capo Optional capo fret number
	 * @param int|null $targetTempoBpm Optional target practice tempo in BPM
	 * @param string|null $difficulty Optional difficulty: "Beginner", "Intermediate" or "Advanced"
	 * @param list<int>|null $tagIds Optional list of workspace tag IDs to apply to the new lecture
	 */
	#[McpTool(name: 'create_lecture', description: 'Create a lecture in a course. Lands in "To Learn" status by default.')]
	public function createLecture(
		int $courseId,
		string $name,
		?string $description = null,
		?string $status = null,
		?string $tuning = null,
		?int $capo = null,
		?int $targetTempoBpm = null,
		?string $difficulty = null,
		?array $tagIds = null,
	): McpLectureDto {
		$user = $this->userContext->getUser();
		$course = $this->requireCourse($courseId);
		$this->requireTeacher($course->workspace);
		$statusEnum = $status !== null && $status !== '' ? $this->parseStatus($status) : LearningStatusEnum::ToLearn;

		$lecture = $this->lectureProvider->createLecture(
			author: $user,
			course: $course,
			status: $statusEnum,
			name: $name,
			description: $description,
			tagIds: $tagIds,
			tuning: $tuning,
			capo: $capo,
			targetTempoBpm: $targetTempoBpm,
			difficulty: self::parseDifficulty($difficulty),
		);

		return McpLectureDto::fromEntity($lecture, $this->lectureTagProvider->getTagIdsForLecture($lecture));
	}

	/**
	 * Update a lecture's editable fields. Omitted parameters are left unchanged.
	 *
	 * @param int|string $lectureId Lecture ID or code (e.g. "MP-3")
	 * @param string|null $name New name
	 * @param string|null $description New description
	 * @param string|null $tuning New tuning; empty string clears it
	 * @param int|null $capo New capo fret number
	 * @param int|null $targetTempoBpm New target tempo in BPM
	 * @param string|null $difficulty New difficulty ("Beginner"|"Intermediate"|"Advanced"); empty string clears it
	 * @param list<int>|null $tagIds Optional list of workspace tag IDs to apply (replaces the full set)
	 */
	#[McpTool(name: 'update_lecture', description: 'Update a lecture. Use move_lecture to change its status.')]
	public function updateLecture(
		int|string $lectureId,
		?string $name = null,
		?string $description = null,
		?string $tuning = null,
		?int $capo = null,
		?int $targetTempoBpm = null,
		?string $difficulty = null,
		?array $tagIds = null,
	): McpLectureDto {
		$user = $this->userContext->getUser();
		$lecture = $this->requireLecture($lectureId);
		$this->requireTeacher($lecture->course->workspace);

		$updated = $this->lectureProvider->updateLecture(
			author: $user,
			lecture: $lecture,
			name: $name ?? $lecture->name,
			description: $description ?? $lecture->description,
			status: $lecture->status,
			tagIds: $tagIds,
			tuning: self::resolveStringField($lecture->tuning, $tuning),
			capo: $capo ?? $lecture->capo,
			targetTempoBpm: $targetTempoBpm ?? $lecture->targetTempoBpm,
			difficulty: $difficulty === null
				? $lecture->difficulty
				: self::parseDifficulty($difficulty),
		);

		return McpLectureDto::fromEntity($updated, $this->lectureTagProvider->getTagIdsForLecture($updated));
	}

	/**
	 * Move a lecture to a different status (column). The lecture is appended to the end of the destination column.
	 *
	 * @param int|string $lectureId Lecture ID or code (e.g. "MP-3")
	 * @param string $status Target status: "To Learn", "Learning" or "Mastered"
	 */
	#[McpTool(name: 'move_lecture', description: 'Move a lecture to a different status. Appends to the end of the destination column.')]
	public function moveLecture(int|string $lectureId, string $status): McpLectureDto
	{
		$user = $this->userContext->getUser();
		$lecture = $this->requireLecture($lectureId);
		$this->requireTeacher($lecture->course->workspace);
		$statusEnum = $this->parseStatus($status);

		$position = $this->lectureProvider->nextPosition($lecture->course, $statusEnum);
		$moved = $this->lectureProvider->moveLecture($user, $lecture, $statusEnum, $position);

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
		$this->requireTeacher($lecture->course->workspace);

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
		$this->requireTeacher($lecture->course->workspace);

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
		$this->requireTeacher($lecture->course->workspace);

		$this->lectureProvider->deleteLecture($user, $lecture);

		return 'Lecture deleted.';
	}

	/**
	 * Apply one operation to many lectures in the current workspace in a single batch.
	 * Per-lecture failures (not found, out of workspace) are returned as `skipped` —
	 * the call succeeds even if some ids could not be processed. Up to 200 ids per call.
	 *
	 * Operations and required `payload`:
	 * - "move": `{status: string}` — moves each lecture to the given status ("To Learn"|"Learning"|"Mastered"),
	 *   appended to end of column
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
		$this->requireTeacher($workspace);

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

	private function parseStatus(string $raw): LearningStatusEnum
	{
		return LearningStatusEnum::fromLoose($raw)
			?? throw new RuntimeException(sprintf('Invalid status "%s"; expected "To Learn", "Learning" or "Mastered".', $raw));
	}

	/** Partial-update string semantics: null leaves the value unchanged, '' clears it, otherwise sets it. */
	private static function resolveStringField(?string $current, ?string $value): ?string
	{
		if ($value === null) {
			return $current;
		}
		return $value === '' ? null : $value;
	}

	private static function parseDifficulty(?string $raw): ?DifficultyEnum
	{
		if ($raw === null || $raw === '') {
			return null;
		}
		return DifficultyEnum::tryFrom($raw)
			?? throw new RuntimeException('Invalid difficulty; expected Beginner, Intermediate or Advanced.');
	}
}
