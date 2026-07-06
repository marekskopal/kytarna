<?php

declare(strict_types=1);

namespace Kytarna\Model\Entity;

use Kytarna\Model\Repository\SongWatcherRepository;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;

/**
 * A user subscribed to a song's activity (U-83, Trello-style). Watchers are added automatically when
 * a user is assigned, comments, or is mentioned, and can be toggled manually. They receive
 * comment / move / due-date notifications for the song.
 */
#[Entity(repositoryClass: SongWatcherRepository::class)]
class SongWatcher extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Song::class)]
		public readonly Song $song,
		#[ManyToOne(entityClass: User::class)]
		public readonly User $user,
	) {
	}
}
