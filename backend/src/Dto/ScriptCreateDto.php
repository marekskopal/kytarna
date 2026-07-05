<?php

declare(strict_types=1);

namespace Ukolio\Dto;

/** @implements ArrayFactoryInterface<array{name: string, source: string, trigger: string, triggerConfig?: string|null, active?: bool}> */
final readonly class ScriptCreateDto implements ArrayFactoryInterface
{
	public function __construct(
		public string $name,
		public string $source,
		public string $trigger,
		public ?string $triggerConfig = null,
		public bool $active = true,
	) {
	}

	public static function fromArray(array $data): static
	{
		return new self(
			name: $data['name'],
			source: $data['source'],
			trigger: $data['trigger'],
			triggerConfig: $data['triggerConfig'] ?? null,
			active: $data['active'] ?? true,
		);
	}
}
