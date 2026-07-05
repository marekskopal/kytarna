<?php

declare(strict_types=1);

namespace Ukolio\Tests\Service\Authentication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ukolio\Service\Authentication\RateLimitConfig;

#[CoversClass(RateLimitConfig::class)]
final class RateLimitConfigTest extends TestCase
{
	private const string PasswordResetEnv = 'RATE_LIMIT_PASSWORD_RESETS_PER_HOUR';

	protected function tearDown(): void
	{
		putenv(self::PasswordResetEnv);

		parent::tearDown();
	}

	public function testPasswordResetsDefaultToFivePerHour(): void
	{
		putenv(self::PasswordResetEnv);

		self::assertSame(5, RateLimitConfig::fromEnv()->passwordResetsPerHour);
	}

	public function testPasswordResetsHonourEnvOverride(): void
	{
		putenv(self::PasswordResetEnv . '=12');

		self::assertSame(12, RateLimitConfig::fromEnv()->passwordResetsPerHour);
	}

	public function testPasswordResetsFallBackToDefaultWhenNonPositive(): void
	{
		putenv(self::PasswordResetEnv . '=0');

		self::assertSame(5, RateLimitConfig::fromEnv()->passwordResetsPerHour);
	}
}
