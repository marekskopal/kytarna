<?php

declare(strict_types=1);

namespace Kytarna\Model\Entity;

use Kytarna\Model\Entity\Enum\LearningStatusEnum;
use Kytarna\Model\Repository\SongBoardStatusRepository;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;

/**
 * A single learner's personal board column for a song (ToLearn / Learning / Mastered).
 * One row per (user, song). Mirrors {@see LectureBoardStatus} — the song's own status is the
 * teacher-authored default; this per-user overlay drives the board column the viewing user sees.
 * Distinct from {@see SongProgressEntry}, which is the dated practice log.
 */
#[Entity(repositoryClass: SongBoardStatusRepository::class)]
class SongBoardStatus extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: User::class)]
		public readonly User $user,
		#[ManyToOne(entityClass: Song::class)]
		public readonly Song $song,
		#[ColumnEnum(enum: LearningStatusEnum::class)]
		public LearningStatusEnum $status,
	) {
	}
}
