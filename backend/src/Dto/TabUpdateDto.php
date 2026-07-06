<?php

declare(strict_types=1);

namespace Kytarna\Dto;

/** @implements ArrayFactoryInterface<array{name: string, alphaTex: string}> */
final readonly class TabUpdateDto implements ArrayFactoryInterface
{
	public function __construct(public string $name, public string $alphaTex)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(name: $data['name'], alphaTex: $data['alphaTex']);
	}
}
