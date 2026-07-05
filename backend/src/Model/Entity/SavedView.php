<?php

declare(strict_types=1);

namespace Kytario\Model\Entity;

use Kytario\Model\Repository\SavedViewRepository;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;

#[Entity(repositoryClass: SavedViewRepository::class)]
class SavedView extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Workspace::class)]
		public readonly Workspace $workspace,
		#[ManyToOne(entityClass: User::class)]
		public readonly User $user,
		#[Column(type: Type::String)]
		public string $name,
		#[Column(type: Type::Text)]
		public string $filterConfig,
	) {
	}
}
