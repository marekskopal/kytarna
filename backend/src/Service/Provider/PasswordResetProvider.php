<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use RuntimeException;
use SensitiveParameter;
use Ukolio\Dto\PasswordResetQueueDto;
use Ukolio\Model\Entity\PasswordResetToken;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\PasswordResetTokenRepository;
use Ukolio\Service\Authentication\RateLimitConfig;
use Ukolio\Service\Queue\Enum\QueueEnum;
use Ukolio\Service\Queue\QueuePublisher;
use const FILTER_VALIDATE_EMAIL;

final readonly class PasswordResetProvider implements PasswordResetProviderInterface
{
	private const string ResetLifetime = '+1 hour';

	public function __construct(
		private PasswordResetTokenRepository $tokenRepository,
		private UserProviderInterface $userProvider,
		private QueuePublisher $queuePublisher,
		private RateLimitConfig $rateLimitConfig,
	) {
	}

	public function requestReset(string $email): void
	{
		$email = mb_strtolower(trim($email));
		if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
			return;
		}

		$user = $this->userProvider->getUserByEmail($email);
		if ($user === null) {
			return;
		}

		// Silently cap resets per user/hour: bounds both the email-bomb blast radius and
		// unbounded token-row growth without leaking whether the account exists.
		$recentCount = $this->tokenRepository->countByUserSince(
			$user->id,
			(new DateTimeImmutable())->modify('-1 hour'),
		);
		if ($recentCount >= $this->rateLimitConfig->passwordResetsPerHour) {
			return;
		}

		$token = bin2hex(random_bytes(32));

		$now = new DateTimeImmutable();
		$resetToken = new PasswordResetToken(
			user: $user,
			tokenHash: hash('sha256', $token),
			expiresAt: $now->modify(self::ResetLifetime),
		);
		$resetToken->createdAt = $now;
		$resetToken->updatedAt = $now;

		$this->tokenRepository->persist($resetToken);

		$this->queuePublisher->publishMessage(
			PasswordResetQueueDto::fromUser($user, $token),
			QueueEnum::PasswordReset,
		);
	}

	public function findByToken(string $token): ?PasswordResetToken
	{
		return $this->tokenRepository->findByTokenHash(hash('sha256', $token));
	}

	public function confirmReset(PasswordResetToken $token, #[SensitiveParameter] string $newPassword): User
	{
		if ($token->usedAt !== null) {
			throw new RuntimeException('This reset link has already been used.');
		}

		if ($token->expiresAt < new DateTimeImmutable()) {
			throw new RuntimeException('This reset link has expired.');
		}

		$user = $this->userProvider->updateUserPassword($token->user, $newPassword);

		$now = new DateTimeImmutable();
		$token->usedAt = $now;
		$token->updatedAt = $now;
		$this->tokenRepository->persist($token);

		return $user;
	}
}
