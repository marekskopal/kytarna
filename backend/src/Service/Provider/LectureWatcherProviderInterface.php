<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use Kytario\Model\Entity\Lecture;
use Kytario\Model\Entity\LectureWatcher;
use Kytario\Model\Entity\User;

interface LectureWatcherProviderInterface
{
	/** Idempotent — returns the existing watch row if the user already watches the lecture. */
	public function watch(Lecture $lecture, User $user): LectureWatcher;

	public function unwatch(Lecture $lecture, User $user): void;

	public function isWatching(Lecture $lecture, User $user): bool;

	/** @return list<LectureWatcher> */
	public function listWatchers(Lecture $lecture): array;

	/** @return list<int> */
	public function listWatcherUserIds(Lecture $lecture): array;

	public function deleteAllForLecture(Lecture $lecture): void;
}
