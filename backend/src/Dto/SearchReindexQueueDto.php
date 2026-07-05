<?php

declare(strict_types=1);

namespace Ukolio\Dto;

/** @implements ArrayFactoryInterface<array{taskId: int, op: string}> */
final readonly class SearchReindexQueueDto implements ArrayFactoryInterface
{
	public const string OpUpsert = 'upsert';
	public const string OpDelete = 'delete';

	public function __construct(public int $taskId, public string $op,)
	{
	}

	public static function upsert(int $taskId): self
	{
		return new self($taskId, self::OpUpsert);
	}

	public static function delete(int $taskId): self
	{
		return new self($taskId, self::OpDelete);
	}

	public static function fromArray(array $data): static
	{
		$op = $data['op'] === self::OpDelete ? self::OpDelete : self::OpUpsert;
		return new self(taskId: $data['taskId'], op: $op);
	}
}
