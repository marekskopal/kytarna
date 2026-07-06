<?php

declare(strict_types=1);

namespace Kytarna\Tests\Service\Provider;

use DateTimeImmutable;
use Kytarna\Model\Repository\PasswordResetTokenRepository;
use Kytarna\Service\Authentication\RateLimitConfig;
use Kytarna\Service\Provider\PasswordResetProvider;
use Kytarna\Service\Provider\PasswordResetProviderInterface;
use Kytarna\Tests\Support\Fixture;
use Kytarna\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PasswordResetProvider::class)]
final class PasswordResetProviderTest extends IntegrationTestCase
{
	private function provider(): PasswordResetProviderInterface
	{
		$provider = $this->container->get(PasswordResetProviderInterface::class);
		assert($provider instanceof PasswordResetProviderInterface);

		return $provider;
	}

	private function tokenRepository(): PasswordResetTokenRepository
	{
		$repository = $this->container->get(PasswordResetTokenRepository::class);
		assert($repository instanceof PasswordResetTokenRepository);

		return $repository;
	}

	private function limit(): int
	{
		$config = $this->container->get(RateLimitConfig::class);
		assert($config instanceof RateLimitConfig);

		return $config->passwordResetsPerHour;
	}

	public function testRequestResetIsCappedPerUserPerHour(): void
	{
		$limit = $this->limit();
		$user = Fixture::createUser(email: 'reset-target@example.com');

		// Fire well past the limit; the surplus requests must be silently dropped.
		for ($i = 0; $i < $limit + 3; $i++) {
			$this->provider()->requestReset('reset-target@example.com');
		}

		$count = $this->tokenRepository()->countByUserSince(
			$user->id,
			(new DateTimeImmutable())->modify('-1 hour'),
		);

		self::assertSame($limit, $count, 'Password-reset tokens must be capped at the configured hourly limit.');
	}

	public function testRequestResetForUnknownEmailPersistsNothing(): void
	{
		$this->provider()->requestReset('nobody@example.com');

		self::assertSame(0, $this->countAllTokens());
	}

	private function countAllTokens(): int
	{
		$pdo = $this->app->dbContext->getDatabase()->getPdo();
		$stmt = $pdo->query('SELECT COUNT(*) FROM password_reset_tokens');
		self::assertNotFalse($stmt);

		return (int) $stmt->fetchColumn();
	}
}
