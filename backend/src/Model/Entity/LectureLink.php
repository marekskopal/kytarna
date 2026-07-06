<?php

declare(strict_types=1);

namespace Kytarna\Model\Entity;

use Kytarna\Model\Entity\Enum\LectureLinkKindEnum;
use Kytarna\Model\Repository\LectureLinkRepository;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;

#[Entity(repositoryClass: LectureLinkRepository::class)]
class LectureLink extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Lecture::class)]
		public readonly Lecture $lecture,
		#[Column(type: Type::String, size: 2048)]
		public string $url,
		#[ColumnEnum(enum: LectureLinkKindEnum::class)]
		public LectureLinkKindEnum $kind,
		#[Column(type: Type::String, nullable: true)]
		public ?string $label = null,
		#[Column(type: Type::Int, nullable: true)]
		public ?int $timestampSeconds = null,
	) {
	}
}
