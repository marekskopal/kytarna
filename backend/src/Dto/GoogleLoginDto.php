<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use SensitiveParameter;

/** @implements ArrayFactoryInterface<array{idToken: string, locale?: string}> */
final readonly class GoogleLoginDto implements ArrayFactoryInterface
{
	public function __construct(#[SensitiveParameter] public string $idToken, public ?string $locale = null,)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(idToken: $data['idToken'], locale: $data['locale'] ?? null);
	}
}
