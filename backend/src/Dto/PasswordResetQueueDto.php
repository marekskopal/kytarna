<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\Enum\LocaleEnum;
use Ukolio\Model\Entity\User;

/**
 * @implements ArrayFactoryInterface<array{
 *     recipientEmail: string,
 *     userName: string,
 *     token: string,
 *     locale: value-of<LocaleEnum>,
 * }>
 */
final readonly class PasswordResetQueueDto implements ArrayFactoryInterface
{
	public function __construct(public string $recipientEmail, public string $userName, public string $token, public LocaleEnum $locale,)
	{
	}

	public static function fromUser(User $user, string $token): self
	{
		return new self(recipientEmail: $user->email, userName: $user->name, token: $token, locale: $user->locale);
	}

	public static function fromArray(array $data): static
	{
		return new self(
			recipientEmail: $data['recipientEmail'],
			userName: $data['userName'],
			token: $data['token'],
			locale: LocaleEnum::from($data['locale']),
		);
	}
}
