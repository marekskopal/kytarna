<?php

declare(strict_types=1);

namespace Kytarna\Service\Notification;

use Kytarna\Model\Entity\Enum\ActorTypeEnum;
use Kytarna\Model\Entity\Enum\EventTypeEnum;
use Kytarna\Model\Entity\Enum\NotificationTypeEnum;
use Kytarna\Model\Entity\Event;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Repository\LectureRepository;
use Kytarna\Model\Repository\UserRepository;
use Kytarna\Service\Provider\LectureWatcherProviderInterface;
use Kytarna\Service\Provider\NotificationProviderInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Turns audit events into per-user notifications (U-83). Hangs off EventProvider::recordEvent.
 * Recipients are the lecture's watchers; the actor is never notified about their own action.
 * To curb agent churn, LectureMoved notifications are suppressed when the move was made by an agent.
 */
final readonly class NotificationDispatcher implements NotificationDispatcherInterface
{
	private const array RelevantTypes = [
		EventTypeEnum::LectureMoved,
	];

	public function __construct(
		private NotificationProviderInterface $notificationProvider,
		private LectureWatcherProviderInterface $lectureWatcherProvider,
		private LectureRepository $lectureRepository,
		private UserRepository $userRepository,
		private LoggerInterface $logger,
	) {
	}

	public function onEvent(Event $event): void
	{
		if (!in_array($event->type, self::RelevantTypes, true) || $event->lectureId === null) {
			return;
		}

		try {
			$lecture = $this->lectureRepository->findById($event->lectureId);
			if ($lecture === null) {
				return;
			}

			$actorId = $event->author?->id;
			$actorName = $event->author?->name;
			$metadata = $this->decodeMetadata($event->metadata);

			// Agents churn statuses; only humans moving a lecture should ping watchers.
			// (The RelevantTypes guard above already limits this to LectureMoved.)
			if ($event->actorType !== ActorTypeEnum::Agent) {
				$this->handleMoved($lecture, $actorId, $actorName, $metadata);
			}
		} catch (Throwable $e) {
			// Fan-out is best-effort; it must never break the mutation that recorded the event.
			$this->logger->error('Notification dispatch failed: ' . $e->getMessage(), ['exception' => $e]);
		}
	}

	/** @param array<string, mixed> $metadata */
	private function handleMoved(Lecture $lecture, ?int $actorId, ?string $actorName, array $metadata): void
	{
		// `status` is a fixed LearningStatusEnum value (ToLearn|Learning|Mastered); the frontend maps it to a label.
		$extra = ['status' => is_string($metadata['toStatus'] ?? null) ? $metadata['toStatus'] : null];

		foreach ($this->recipientIds($lecture) as $userId) {
			if ($userId === $actorId) {
				continue;
			}
			$user = $this->userRepository->findUserById($userId);
			if ($user === null) {
				continue;
			}
			$this->notify($user, NotificationTypeEnum::LectureMoved, $lecture, $actorId, $actorName, $extra);
		}
	}

	/**
	 * Write the notification row and (for emailable types) enqueue an email.
	 *
	 * @param array<string, mixed> $extra
	 */
	private function notify(
		User $recipient,
		NotificationTypeEnum $type,
		Lecture $lecture,
		?int $actorId,
		?string $actorName,
		array $extra,
	): void
	{
		$workspaceId = $lecture->course->workspace->id;
		$courseId = $lecture->course->id;
		$lectureCode = $lecture->course->prefix . '-' . $lecture->sequenceNumber;

		$data = array_merge(['lectureCode' => $lectureCode, 'lectureName' => $lecture->name], array_filter(
			$extra,
			static fn (mixed $value): bool => $value !== null,
		));

		$this->notificationProvider->create($recipient, $workspaceId, $type, $lecture->id, $courseId, $actorId, $actorName, $data);
	}

	/** @return list<int> lecture watchers */
	private function recipientIds(Lecture $lecture): array
	{
		return array_values(array_unique($this->lectureWatcherProvider->listWatcherUserIds($lecture)));
	}

	/** @return array<string, mixed> */
	private function decodeMetadata(string $json): array
	{
		$decoded = json_decode($json, true);
		if (!is_array($decoded)) {
			return [];
		}

		$result = [];
		foreach ($decoded as $key => $value) {
			$result[(string) $key] = $value;
		}
		return $result;
	}
}
