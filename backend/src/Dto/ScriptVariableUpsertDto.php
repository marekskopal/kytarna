<?php

declare(strict_types=1);

namespace Ukolio\Dto;

/** @implements ArrayFactoryInterface<array{key: string, value: string, isSecret?: bool}> */
final readonly class ScriptVariableUpsertDto implements ArrayFactoryInterface
{
	public function __construct(public string $key, public string $value, public bool $isSecret = false,)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(key: $data['key'], value: $data['value'], isSecret: $data['isSecret'] ?? false);
	}
}
