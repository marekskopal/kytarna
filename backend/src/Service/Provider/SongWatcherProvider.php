<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use DateTimeImmutable;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\SongWatcher;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Repository\SongWatcherRepository;

final readonly class SongWatcherProvider implements SongWatcherProviderInterface
{
	public function __construct(private SongWatcherRepository $songWatcherRepository)
	{
	}

	public function watch(Song $song, User $user): SongWatcher
	{
		$existing = $this->songWatcherRepository->findBySongAndUser($song->id, $user->id);
		if ($existing !== null) {
			return $existing;
		}

		$now = new DateTimeImmutable();
		$watcher = new SongWatcher(song: $song, user: $user);
		$watcher->createdAt = $now;
		$watcher->updatedAt = $now;
		$this->songWatcherRepository->persist($watcher);

		return $watcher;
	}

	public function unwatch(Song $song, User $user): void
	{
		$existing = $this->songWatcherRepository->findBySongAndUser($song->id, $user->id);
		if ($existing === null) {
			return;
		}

		$this->songWatcherRepository->delete($existing);
	}

	public function isWatching(Song $song, User $user): bool
	{
		return $this->songWatcherRepository->findBySongAndUser($song->id, $user->id) !== null;
	}

	/** @return list<SongWatcher> */
	public function listWatchers(Song $song): array
	{
		$result = [];
		foreach ($this->songWatcherRepository->findBySong($song->id) as $watcher) {
			$result[] = $watcher;
		}
		return $result;
	}

	/** @return list<int> */
	public function listWatcherUserIds(Song $song): array
	{
		$ids = [];
		foreach ($this->songWatcherRepository->findBySong($song->id) as $watcher) {
			$ids[] = $watcher->user->id;
		}
		return $ids;
	}

	public function deleteAllForSong(Song $song): void
	{
		foreach ($this->songWatcherRepository->findBySong($song->id) as $watcher) {
			$this->songWatcherRepository->delete($watcher);
		}
	}
}
