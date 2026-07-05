<?php

declare(strict_types=1);

namespace Ukolio\Dto;

/** @implements ArrayFactoryInterface<array{name: string}> */
final readonly class TaskTemplateSaveDto implements ArrayFactoryInterface
{
	public function __construct(public string $name)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(name: $data['name']);
	}
}
