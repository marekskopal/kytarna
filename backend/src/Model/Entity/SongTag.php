<?php

declare(strict_types=1);

namespace Kytarna\Model\Entity;

use Kytarna\Model\Repository\SongTagRepository;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;

#[Entity(repositoryClass: SongTagRepository::class)]
class SongTag extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Song::class)]
		public readonly Song $song,
		#[ManyToOne(entityClass: Tag::class)]
		public readonly Tag $tag,
	) {
	}
}
