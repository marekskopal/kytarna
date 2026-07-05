<?php

declare(strict_types=1);

namespace Kytario\Dto;

/** @implements ArrayFactoryInterface<array{name: string, alphaTex: string}> */
final readonly class TabCreateDto implements ArrayFactoryInterface
{
	public function __construct(public string $name, public string $alphaTex)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(name: $data['name'], alphaTex: $data['alphaTex']);
	}
}
