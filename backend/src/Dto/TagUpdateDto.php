<?php

declare(strict_types=1);

namespace Kytario\Dto;

/** @implements ArrayFactoryInterface<array{name: string, color: string}> */
final readonly class TagUpdateDto implements ArrayFactoryInterface
{
	public function __construct(public string $name, public string $color,)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(name: $data['name'], color: $data['color']);
	}
}
