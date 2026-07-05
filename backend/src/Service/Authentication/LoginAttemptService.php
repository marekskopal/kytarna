<?php

declare(strict_types=1);

namespace Ukolio\Service\Authentication;

use DateTimeImmutable;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\UserRepository;
use Ukolio\Service\Authentication\Exception\AccountLockedException;

final readonly class LoginAttemptService
{
	/** Backoff schedule (seconds) once the threshold is exceeded. The last value is repeated until the cap. */
	private const array BackoffSchedule = [60, 120, 300, 900, 1800];

	public function __construct(private UserRepository $userRepository, private RateLimitConfig $config)
	{
	}

	public function assertNotLocked(User $user): void
	{
		if ($user->lockedUntil === null) {
			return;
		}

		$remaining = $user->lockedUntil->getTimestamp() - (new DateTimeImmutable())->getTimestamp();
		if ($remaining <= 0) {
			return;
		}

		throw new AccountLockedException($remaining);
	}

	public function recordFailure(User $user): void
	{
		$user->failedLoginAttempts++;

		$excess = $user->failedLoginAttempts - $this->config->loginThreshold;
		if ($excess >= 0) {
			$user->lockedUntil = (new DateTimeImmutable())->modify('+' . $this->backoffSeconds($excess) . ' seconds');
		}

		$this->userRepository->persist($user);
	}

	public function recordSuccess(User $user): void
	{
		if ($user->failedLoginAttempts === 0 && $user->lockedUntil === null) {
			return;
		}

		$user->failedLoginAttempts = 0;
		$user->lockedUntil = null;
		$this->userRepository->persist($user);
	}

	private function backoffSeconds(int $excess): int
	{
		$schedule = self::BackoffSchedule;
		$seconds = $schedule[$excess] ?? $schedule[count($schedule) - 1];

		return min($seconds, $this->config->loginBackoffCapSeconds);
	}
}
