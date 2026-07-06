<?php

declare(strict_types=1);

namespace Kytarna\Model\Entity;

use Kytarna\Model\Entity\Enum\LearningStatusEnum;
use Kytarna\Model\Repository\LectureBoardStatusRepository;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;

/**
 * A single learner's personal board column for a lecture (ToLearn / Learning / Mastered).
 * One row per (user, lecture). The lecture's own {@see Lecture::$status} is the teacher-authored
 * default/template; this per-user overlay is what actually drives the board column the viewing
 * user sees. Distinct from {@see ProgressEntry}, which is the dated practice log.
 */
#[Entity(repositoryClass: LectureBoardStatusRepository::class)]
class LectureBoardStatus extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: User::class)]
		public readonly User $user,
		#[ManyToOne(entityClass: Lecture::class)]
		public readonly Lecture $lecture,
		#[ColumnEnum(enum: LearningStatusEnum::class)]
		public LearningStatusEnum $status,
	) {
	}
}
