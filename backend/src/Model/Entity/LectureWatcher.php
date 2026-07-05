<?php

declare(strict_types=1);

namespace Kytario\Model\Entity;

use Kytario\Model\Repository\LectureWatcherRepository;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;

/**
 * A user subscribed to a lecture's activity (U-83, Trello-style). Watchers are added automatically when
 * a user is assigned, comments, or is mentioned, and can be toggled manually. They receive
 * comment / move / due-date notifications for the lecture.
 */
#[Entity(repositoryClass: LectureWatcherRepository::class)]
class LectureWatcher extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Lecture::class)]
		public readonly Lecture $lecture,
		#[ManyToOne(entityClass: User::class)]
		public readonly User $user,
	) {
	}
}
