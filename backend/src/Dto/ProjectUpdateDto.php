<?php

declare(strict_types=1);

namespace Ukolio\Dto;

/** @implements ArrayFactoryInterface<array{name?: string, description?: ?string}> */
final readonly class ProjectUpdateDto implements ArrayFactoryInterface
{
	public function __construct(public ?string $name, public ?string $description)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(name: $data['name'] ?? null, description: $data['description'] ?? null);
	}
}
