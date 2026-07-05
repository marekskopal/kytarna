<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;
use Ukolio\Model\Repository\ScriptVariableRepository;

#[Entity(repositoryClass: ScriptVariableRepository::class)]
class ScriptVariable extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Workspace::class)]
		public readonly Workspace $workspace,
		#[Column(type: Type::String)]
		public string $key,
		/** Stored verbatim when isSecret is false; AES-256-GCM ciphertext (see SecretCipher) when isSecret is true. */
		#[Column(type: Type::Text)]
		public string $value,
		#[Column(type: Type::Boolean, default: false)]
		public bool $isSecret = false,
	) {
	}
}
