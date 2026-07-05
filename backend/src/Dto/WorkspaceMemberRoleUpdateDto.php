<?php

declare(strict_types=1);

namespace Ukolio\Dto;

/** @implements ArrayFactoryInterface<array{role: string}> */
final readonly class WorkspaceMemberRoleUpdateDto implements ArrayFactoryInterface
{
	public function __construct(public string $role)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(role: $data['role']);
	}
}
