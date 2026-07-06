<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use DateTimeImmutable;
use Kytarna\Dto\EmailVerificationQueueDto;
use Kytarna\Model\Entity\EmailVerificationToken;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Repository\EmailVerificationTokenRepository;
use Kytarna\Service\Queue\Enum\QueueEnum;
use Kytarna\Service\Queue\QueuePublisher;
use RuntimeException;

final readonly class EmailVerificationProvider implements EmailVerificationProviderInterface
{
	private const string VerificationLifetime = '+24 hours';

	public function __construct(
		private EmailVerificationTokenRepository $tokenRepository,
		private UserProviderInterface $userProvider,
		private QueuePublisher $queuePublisher,
	) {
	}

	public function requestVerification(User $user): void
	{
		if ($user->emailVerified) {
			return;
		}

		$token = bin2hex(random_bytes(32));

		$now = new DateTimeImmutable();
		$verificationToken = new EmailVerificationToken(
			user: $user,
			tokenHash: hash('sha256', $token),
			expiresAt: $now->modify(self::VerificationLifetime),
		);
		$verificationToken->createdAt = $now;
		$verificationToken->updatedAt = $now;

		$this->tokenRepository->persist($verificationToken);

		$this->queuePublisher->publishMessage(
			EmailVerificationQueueDto::fromUser($user, $token),
			QueueEnum::EmailVerification,
		);
	}

	public function findByToken(string $token): ?EmailVerificationToken
	{
		return $this->tokenRepository->findByTokenHash(hash('sha256', $token));
	}

	public function confirmVerification(EmailVerificationToken $token): User
	{
		if ($token->usedAt !== null) {
			throw new RuntimeException('This verification link has already been used.');
		}

		if ($token->expiresAt < new DateTimeImmutable()) {
			throw new RuntimeException('This verification link has expired.');
		}

		$user = $this->userProvider->markEmailVerified($token->user);

		$now = new DateTimeImmutable();
		$token->usedAt = $now;
		$token->updatedAt = $now;
		$this->tokenRepository->persist($token);

		return $user;
	}
}
