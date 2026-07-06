<?php

declare(strict_types=1);

namespace Kytarna\Dto;

/** @implements ArrayFactoryInterface<array{email: string, role?: string}> */
final readonly class InvitationCreateDto implements ArrayFactoryInterface
{
	public function __construct(public string $email, public string $role)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(email: $data['email'], role: $data['role'] ?? 'Member');
	}
}
