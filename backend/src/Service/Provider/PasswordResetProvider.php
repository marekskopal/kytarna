<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use DateTimeImmutable;
use Kytarna\Dto\PasswordResetQueueDto;
use Kytarna\Model\Entity\PasswordResetToken;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Repository\PasswordResetTokenRepository;
use Kytarna\Service\Authentication\RateLimitConfig;
use Kytarna\Service\Queue\Enum\QueueEnum;
use Kytarna\Service\Queue\QueuePublisher;
use RuntimeException;
use SensitiveParameter;
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
