<?php

declare(strict_types=1);

namespace Ukolio\Dto;

/** @implements ArrayFactoryInterface<array{userId: int, role?: string}> */
final readonly class AdminAddMemberDto implements ArrayFactoryInterface
{
	public function __construct(public int $userId, public string $role)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(userId: $data['userId'], role: $data['role'] ?? 'Member');
	}
}
