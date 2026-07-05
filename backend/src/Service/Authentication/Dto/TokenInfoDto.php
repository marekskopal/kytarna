<?php

declare(strict_types=1);

namespace Ukolio\Service\Authentication\Dto;

final readonly class TokenInfoDto
{
	public function __construct(
		public string $sub,
		public string $email,
		public string $name,
		public string $aud,
		public bool $emailVerified,
	) {
	}

	/** @param array{sub: string, email: string, name?: string, aud: string, email_verified?: string|bool} $data */
	public static function fromArray(array $data): self
	{
		$emailVerified = $data['email_verified'] ?? false;

		return new self(
			sub: $data['sub'],
			email: $data['email'],
			name: $data['name'] ?? $data['email'],
			aud: $data['aud'],
			emailVerified: is_string($emailVerified) ? $emailVerified === 'true' : $emailVerified,
		);
	}
}
