<?php

declare(strict_types=1);

namespace Ukolio\Dto;

/** @implements ArrayFactoryInterface<array{statusId: int, position: int}> */
final readonly class TaskMoveDto implements ArrayFactoryInterface
{
	public function __construct(public int $statusId, public int $position)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(statusId: $data['statusId'], position: $data['position']);
	}
}
