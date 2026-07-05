<?php

declare(strict_types=1);

namespace Ukolio\Dto;

/** @implements ArrayFactoryInterface<array{name?: string, email?: string, systemRole?: string}> */
final readonly class AdminUserUpdateDto implements ArrayFactoryInterface
{
	public function __construct(public ?string $name, public ?string $email, public ?string $systemRole,)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(name: $data['name'] ?? null, email: $data['email'] ?? null, systemRole: $data['systemRole'] ?? null);
	}
}
