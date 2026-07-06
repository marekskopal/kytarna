<?php

declare(strict_types=1);

namespace Kytarna\Dto;

use SensitiveParameter;

/** @implements ArrayFactoryInterface<array{email: string, password: string, name: string, locale?: string}> */
final readonly class SignUpDto implements ArrayFactoryInterface
{
	public function __construct(
		#[SensitiveParameter] public string $email,
		#[SensitiveParameter] public string $password,
		public string $name,
		public ?string $locale = null,
	) {
	}

	public static function fromArray(array $data): static
	{
		return new self(email: $data['email'], password: $data['password'], name: $data['name'], locale: $data['locale'] ?? null);
	}
}
