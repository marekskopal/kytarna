<?php

declare(strict_types=1);

namespace Ukolio\Dto;

/** @implements ArrayFactoryInterface<array{name: string, description?: ?string}> */
final readonly class ProjectCreateDto implements ArrayFactoryInterface
{
	public function __construct(public string $name, public ?string $description)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(name: $data['name'], description: $data['description'] ?? null);
	}
}
