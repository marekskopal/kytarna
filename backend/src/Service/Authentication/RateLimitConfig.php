<?php

declare(strict_types=1);

namespace Ukolio\Service\Authentication;

final readonly class RateLimitConfig
{
	public function __construct(
		public int $loginThreshold,
		public int $loginBackoffCapSeconds,
		public int $invitationsPerHour,
		public int $passwordResetsPerHour,
	) {
	}

	public static function fromEnv(): self
	{
		$loginThresholdRaw = (string) getenv('RATE_LIMIT_LOGIN_ATTEMPTS');
		$loginThreshold = $loginThresholdRaw === '' ? 5 : (int) $loginThresholdRaw;
		if ($loginThreshold < 1) {
			$loginThreshold = 5;
		}

		$capRaw = (string) getenv('RATE_LIMIT_LOGIN_BACKOFF_CAP_SECONDS');
		$cap = $capRaw === '' ? 3600 : (int) $capRaw;
		if ($cap < 60) {
			$cap = 3600;
		}

		$invitationsRaw = (string) getenv('RATE_LIMIT_INVITATIONS_PER_HOUR');
		$invitationsPerHour = $invitationsRaw === '' ? 50 : (int) $invitationsRaw;
		if ($invitationsPerHour < 1) {
			$invitationsPerHour = 50;
		}

		$passwordResetsRaw = (string) getenv('RATE_LIMIT_PASSWORD_RESETS_PER_HOUR');
		$passwordResetsPerHour = $passwordResetsRaw === '' ? 5 : (int) $passwordResetsRaw;
		if ($passwordResetsPerHour < 1) {
			$passwordResetsPerHour = 5;
		}

		return new self(
			loginThreshold: $loginThreshold,
			loginBackoffCapSeconds: $cap,
			invitationsPerHour: $invitationsPerHour,
			passwordResetsPerHour: $passwordResetsPerHour,
		);
	}
}
