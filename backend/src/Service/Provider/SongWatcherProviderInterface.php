<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\SongWatcher;
use Kytarna\Model\Entity\User;

interface SongWatcherProviderInterface
{
	/** Idempotent — returns the existing watch row if the user already watches the song. */
	public function watch(Song $song, User $user): SongWatcher;

	public function unwatch(Song $song, User $user): void;

	public function isWatching(Song $song, User $user): bool;

	/** @return list<SongWatcher> */
	public function listWatchers(Song $song): array;

	/** @return list<int> */
	public function listWatcherUserIds(Song $song): array;

	public function deleteAllForSong(Song $song): void;
}
