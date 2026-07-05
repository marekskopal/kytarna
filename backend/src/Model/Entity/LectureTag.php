<?php

declare(strict_types=1);

namespace Kytario\Model\Entity;

use Kytario\Model\Repository\LectureTagRepository;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;

#[Entity(repositoryClass: LectureTagRepository::class)]
class LectureTag extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Lecture::class)]
		public readonly Lecture $lecture,
		#[ManyToOne(entityClass: Tag::class)]
		public readonly Tag $tag,
	) {
	}
}
