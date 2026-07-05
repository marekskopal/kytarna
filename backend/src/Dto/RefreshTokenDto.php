<?php

declare(strict_types=1);

namespace Ukolio\Dto;

/** @implements ArrayFactoryInterface<array{refreshToken: string}> */
final readonly class RefreshTokenDto implements ArrayFactoryInterface
{
	public function __construct(public string $refreshToken)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(refreshToken: $data['refreshToken']);
	}
}
