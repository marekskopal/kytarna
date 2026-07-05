<?php

declare(strict_types=1);

namespace Kytario\Model\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;
use Kytario\Model\Repository\WorkspaceRepository;

#[Entity(repositoryClass: WorkspaceRepository::class)]
class Workspace extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: User::class)]
		public User $owner,
		#[Column(type: Type::String)]
		public string $name,
	) {
	}
}
