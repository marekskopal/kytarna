<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Kytarna\Model\Entity\Enum\EventTypeEnum;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\Status;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Model\Repository\LectureRepository;
use Kytarna\Service\Provider\Enum\BulkOpEnum;
use RuntimeException;

final readonly class BulkLectureProvider implements BulkLectureProviderInterface
{
	public const int MAX_IDS = 200;

	public function __construct(
		private LectureRepository $lectureRepository,
		private LectureProviderInterface $lectureProvider,
		private LectureTagProviderInterface $lectureTagProvider,
		private StatusProviderInterface $statusProvider,
		private EventProviderInterface $eventProvider,
		private BulkPayloadParser $payloadParser,
	) {
	}

	/**
	 * @param list<int> $ids
	 * @param array<string, mixed> $payload
	 * @return array{succeeded: list<int>, skipped: list<array{id: int, reason: string}>}
	 */
	public function execute(User $actor, Workspace $workspace, BulkOpEnum $op, array $ids, array $payload): array
	{
		$ids = $this->normaliseIds($ids);
		$context = $this->resolvePayloadContext($workspace, $op, $payload);
		$lecturesById = $this->loadLecturesById($ids);

		$succeeded = [];
		$skipped = [];
		foreach ($ids as $id) {
			$outcome = $this->processOne($actor, $workspace, $op, $context, $lecturesById[$id] ?? null, $id);
			if ($outcome === null) {
				$succeeded[] = $id;
			} else {
				$skipped[] = ['id' => $id, 'reason' => $outcome];
			}
		}

		$this->eventProvider->recordWorkspaceEvent(
			$actor,
			$workspace,
			EventTypeEnum::LecturesBulkUpdated,
			[
				'op' => $op->value,
				'payload' => $this->payloadParser->sanitise($payload),
				'succeededIds' => $succeeded,
				'skipped' => $skipped,
			],
		);

		return ['succeeded' => $succeeded, 'skipped' => $skipped];
	}

	/**
	 * @param list<int> $ids
	 * @return list<int>
	 */
	private function normaliseIds(array $ids): array
	{
		$cleaned = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
		if ($cleaned === []) {
			throw new RuntimeException('No ids provided.');
		}
		if (count($cleaned) > self::MAX_IDS) {
			throw new RuntimeException(sprintf('Too many ids (max %d).', self::MAX_IDS));
		}
		return $cleaned;
	}

	/**
	 * @param list<int> $ids
	 * @return array<int, Lecture>
	 */
	private function loadLecturesById(array $ids): array
	{
		$lecturesById = [];
		foreach ($this->lectureRepository->findByIds($ids) as $lecture) {
			$lecturesById[$lecture->id] = $lecture;
		}
		return $lecturesById;
	}

	/**
	 * @param array{status?: Status, tagIds?: list<int>} $context
	 * @return non-empty-string|null null on success, reason string on skip
	 */
	private function processOne(User $actor, Workspace $workspace, BulkOpEnum $op, array $context, ?Lecture $lecture, int $id,): ?string
	{
		if ($lecture === null) {
			return 'not_found';
		}
		if ($lecture->course->workspace->id !== $workspace->id) {
			return 'out_of_workspace';
		}

		try {
			$this->applyOp($actor, $lecture, $op, $context);
			return null;
		} catch (RuntimeException $e) {
			$reason = $e->getMessage();
			return $reason === '' ? 'failed' : $reason;
		}
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array{status?: Status, tagIds?: list<int>}
	 */
	private function resolvePayloadContext(Workspace $workspace, BulkOpEnum $op, array $payload): array
	{
		return match ($op) {
			BulkOpEnum::Move => ['status' => $this->requireStatus($workspace, $payload)],
			BulkOpEnum::Tag, BulkOpEnum::Untag => ['tagIds' => $this->requireTagIds($payload)],
			BulkOpEnum::Delete => [],
		};
	}

	/** @param array{status?: Status, tagIds?: list<int>} $context */
	private function applyOp(User $actor, Lecture $lecture, BulkOpEnum $op, array $context): void
	{
		match ($op) {
			BulkOpEnum::Move => $this->doMove($actor, $lecture, $context['status'] ?? null),
			BulkOpEnum::Tag => $this->doTagAdd($lecture, $context['tagIds'] ?? []),
			BulkOpEnum::Untag => $this->doTagRemove($lecture, $context['tagIds'] ?? []),
			BulkOpEnum::Delete => $this->lectureProvider->deleteLecture($actor, $lecture, recordEvent: false),
		};
	}

	private function doMove(User $actor, Lecture $lecture, ?Status $status): void
	{
		if ($status === null) {
			throw new RuntimeException('Internal: status not resolved.');
		}
		if ($status->workflow->course->id !== $lecture->course->id) {
			throw new RuntimeException('status_not_in_course');
		}
		$this->lectureProvider->moveLecture($actor, $lecture, $status, $this->lectureProvider->nextPosition($status), recordEvent: false);
	}

	/** @param list<int> $payloadTagIds */
	private function doTagAdd(Lecture $lecture, array $payloadTagIds): void
	{
		$existing = $this->lectureTagProvider->getTagIdsForLecture($lecture);
		$merged = array_values(array_unique(array_merge($existing, $payloadTagIds)));
		$this->lectureTagProvider->setTagsForLecture($lecture->course->workspace, $lecture, $merged);
	}

	/** @param list<int> $payloadTagIds */
	private function doTagRemove(Lecture $lecture, array $payloadTagIds): void
	{
		$existing = $this->lectureTagProvider->getTagIdsForLecture($lecture);
		$kept = array_values(array_diff($existing, $payloadTagIds));
		$this->lectureTagProvider->setTagsForLecture($lecture->course->workspace, $lecture, $kept);
	}

	/** @param array<string, mixed> $payload */
	private function requireStatus(Workspace $workspace, array $payload): Status
	{
		$statusId = $this->payloadParser->intOrNull($payload, 'statusId');
		if ($statusId === null) {
			throw new RuntimeException('Payload missing statusId.');
		}
		$status = $this->statusProvider->getStatus($statusId);
		if ($status === null || $status->workflow->course->workspace->id !== $workspace->id) {
			throw new RuntimeException('Status not found in this workspace.');
		}
		return $status;
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return list<int>
	 */
	private function requireTagIds(array $payload): array
	{
		$tagIds = $this->payloadParser->intList($payload, 'tagIds');
		if ($tagIds === []) {
			throw new RuntimeException('Payload missing tagIds.');
		}
		return $tagIds;
	}
}
