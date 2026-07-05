<?php

declare(strict_types=1);

namespace Kytario\Dto;

/** @implements ArrayFactoryInterface<array{email: string}> */
final readonly class RequestPasswordResetDto implements ArrayFactoryInterface
{
	public function __construct(public string $email)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(email: $data['email']);
	}
}
