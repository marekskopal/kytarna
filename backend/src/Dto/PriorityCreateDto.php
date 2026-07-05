<?php

declare(strict_types=1);

namespace Ukolio\Dto;

/** @implements ArrayFactoryInterface<array{name: string, color: string, isDefault?: bool}> */
final readonly class PriorityCreateDto implements ArrayFactoryInterface
{
	public function __construct(public string $name, public string $color, public bool $isDefault)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(name: $data['name'], color: $data['color'], isDefault: $data['isDefault'] ?? false);
	}
}
