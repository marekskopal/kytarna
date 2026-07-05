<?php

declare(strict_types=1);

namespace Ukolio\Dto;

/** @implements ArrayFactoryInterface<array{position?: int}> */
final readonly class TaskChecklistItemMoveDto implements ArrayFactoryInterface
{
	public function __construct(public int $position)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(position: $data['position'] ?? 0);
	}
}
