<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\Enum\LocaleEnum;
use Ukolio\Model\Entity\Invitation;

/**
 * @implements ArrayFactoryInterface<array{
 *     recipientEmail: string,
 *     workspaceName: string,
 *     inviterName: string,
 *     token: string,
 *     locale: value-of<LocaleEnum>,
 * }>
 */
final readonly class InvitationQueueDto implements ArrayFactoryInterface
{
	public function __construct(
		public string $recipientEmail,
		public string $workspaceName,
		public string $inviterName,
		public string $token,
		public LocaleEnum $locale,
	) {
	}

	public static function fromEntity(Invitation $invitation, string $token, LocaleEnum $locale): self
	{
		return new self(
			recipientEmail: $invitation->email,
			workspaceName: $invitation->workspace->name,
			inviterName: $invitation->inviter->name,
			token: $token,
			locale: $locale,
		);
	}

	public static function fromArray(array $data): static
	{
		return new self(
			recipientEmail: $data['recipientEmail'],
			workspaceName: $data['workspaceName'],
			inviterName: $data['inviterName'],
			token: $data['token'],
			locale: LocaleEnum::from($data['locale']),
		);
	}
}
