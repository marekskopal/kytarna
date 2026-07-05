<?php

declare(strict_types=1);

namespace Kytario\Dto;

use SensitiveParameter;

/** @implements ArrayFactoryInterface<array{currentPassword: string, newPassword: string}> */
final readonly class ChangePasswordDto implements ArrayFactoryInterface
{
	public function __construct(#[SensitiveParameter] public string $currentPassword, #[SensitiveParameter] public string $newPassword,)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(currentPassword: $data['currentPassword'], newPassword: $data['newPassword']);
	}
}
