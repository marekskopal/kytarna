<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\ScriptVariable;
use const DATE_ATOM;

final readonly class ScriptVariableDto
{
	public function __construct(
		public int $id,
		public int $workspaceId,
		public string $key,
		public ?string $value,
		public bool $isSecret,
		public string $updatedAt,
	) {
	}

	/**
	 * Secret values are never returned to clients — only their presence is signalled. Non-secret
	 * values are returned verbatim so the management UI can show them.
	 */
	public static function fromEntity(ScriptVariable $variable): self
	{
		return new self(
			id: $variable->id,
			workspaceId: $variable->workspace->id,
			key: $variable->key,
			value: $variable->isSecret ? null : $variable->value,
			isSecret: $variable->isSecret,
			updatedAt: $variable->updatedAt->format(DATE_ATOM),
		);
	}
}
