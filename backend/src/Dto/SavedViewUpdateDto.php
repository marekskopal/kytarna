<?php

declare(strict_types=1);

namespace Ukolio\Dto;

/** @implements ArrayFactoryInterface<array{name: string, filterConfig: string}> */
final readonly class SavedViewUpdateDto implements ArrayFactoryInterface
{
	public function __construct(public string $name, public string $filterConfig)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(name: $data['name'], filterConfig: $data['filterConfig']);
	}
}
