<?php

declare(strict_types=1);

namespace Kytarna\Model\Entity;

use Kytarna\Model\Repository\WorkspaceRepository;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;

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
