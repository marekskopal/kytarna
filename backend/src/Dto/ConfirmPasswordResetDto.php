<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use SensitiveParameter;

/** @implements ArrayFactoryInterface<array{token: string, password: string}> */
final readonly class ConfirmPasswordResetDto implements ArrayFactoryInterface
{
	public function __construct(public string $token, #[SensitiveParameter] public string $password)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(token: $data['token'], password: $data['password']);
	}
}
