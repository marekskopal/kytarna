<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use DateTimeImmutable;
use Kytario\Model\Entity\Lecture;
use Kytario\Model\Entity\LectureWatcher;
use Kytario\Model\Entity\User;
use Kytario\Model\Repository\LectureWatcherRepository;

final readonly class LectureWatcherProvider implements LectureWatcherProviderInterface
{
	public function __construct(private LectureWatcherRepository $lectureWatcherRepository)
	{
	}

	public function watch(Lecture $lecture, User $user): LectureWatcher
	{
		$existing = $this->lectureWatcherRepository->findByLectureAndUser($lecture->id, $user->id);
		if ($existing !== null) {
			return $existing;
		}

		$now = new DateTimeImmutable();
		$watcher = new LectureWatcher(lecture: $lecture, user: $user);
		$watcher->createdAt = $now;
		$watcher->updatedAt = $now;
		$this->lectureWatcherRepository->persist($watcher);

		return $watcher;
	}

	public function unwatch(Lecture $lecture, User $user): void
	{
		$existing = $this->lectureWatcherRepository->findByLectureAndUser($lecture->id, $user->id);
		if ($existing === null) {
			return;
		}

		$this->lectureWatcherRepository->delete($existing);
	}

	public function isWatching(Lecture $lecture, User $user): bool
	{
		return $this->lectureWatcherRepository->findByLectureAndUser($lecture->id, $user->id) !== null;
	}

	/** @return list<LectureWatcher> */
	public function listWatchers(Lecture $lecture): array
	{
		$result = [];
		foreach ($this->lectureWatcherRepository->findByLecture($lecture->id) as $watcher) {
			$result[] = $watcher;
		}
		return $result;
	}

	/** @return list<int> */
	public function listWatcherUserIds(Lecture $lecture): array
	{
		$ids = [];
		foreach ($this->lectureWatcherRepository->findByLecture($lecture->id) as $watcher) {
			$ids[] = $watcher->user->id;
		}
		return $ids;
	}

	public function deleteAllForLecture(Lecture $lecture): void
	{
		foreach ($this->lectureWatcherRepository->findByLecture($lecture->id) as $watcher) {
			$this->lectureWatcherRepository->delete($watcher);
		}
	}
}
