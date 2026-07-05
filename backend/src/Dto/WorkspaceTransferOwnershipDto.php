<?php

declare(strict_types=1);

namespace Kytario\Dto;

/** @implements ArrayFactoryInterface<array{userId: int}> */
final readonly class WorkspaceTransferOwnershipDto implements ArrayFactoryInterface
{
	public function __construct(public int $userId)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(userId: $data['userId']);
	}
}
