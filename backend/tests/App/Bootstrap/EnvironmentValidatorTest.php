<?php

declare(strict_types=1);

namespace Ukolio\Tests\App\Bootstrap;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Ukolio\App\Bootstrap\EnvironmentValidator;

#[CoversClass(EnvironmentValidator::class)]
final class EnvironmentValidatorTest extends TestCase
{
	private const string ValidTokenKey = '0123456789abcdef0123456789abcdef0123456789abcdef';
	private const string StrongPassword = 'super-strong-password-1234';

	/** @return array<string, string> */
	private static function baseEnv(): array
	{
		return [
			'AUTHORIZATION_TOKEN_KEY' => self::ValidTokenKey,
			'MYSQL_HOST' => 'db',
			'MYSQL_DATABASE' => 'ukolio',
			'MYSQL_USER' => 'ukolio',
			'MYSQL_PASSWORD' => self::StrongPassword,
			'MYSQL_ROOT_PASSWORD' => self::StrongPassword,
			'S3_BUCKET' => 'ukolio-files',
			'S3_ACCESS_KEY' => self::StrongPassword,
			'S3_SECRET_KEY' => self::StrongPassword,
			'REDIS_HOST' => 'redis',
			'REDIS_PORT' => '6379',
			'REDIS_PASSWORD' => self::StrongPassword,
			'RABBITMQ_PASSWORD' => self::StrongPassword,
			'MEILI_MASTER_KEY' => self::StrongPassword,
			'MERCURE_PUBLISHER_JWT_KEY' => self::StrongPassword,
			'MERCURE_SUBSCRIBER_JWT_KEY' => self::StrongPassword,
			'MEMCACHED_HOST' => 'memcached',
			'MEMCACHED_PORT' => '11211',
			'APP_ENV' => 'development',
			'BACKEND_CORS_ALLOWED_ORIGIN' => 'https://app.example.com',
		];
	}

	public function testPassesWithStrongEnv(): void
	{
		$this->expectNotToPerformAssertions();

		$validator = new EnvironmentValidator(self::baseEnv());
		$validator->validate();
	}

	public function testFailsWhenRequiredVariableIsMissing(): void
	{
		$env = self::baseEnv();
		$env['MYSQL_HOST'] = '';

		$validator = new EnvironmentValidator($env);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('Required environment variables are not set: MYSQL_HOST');

		$validator->validate();
	}

	public function testFailsWhenAuthorizationTokenKeyMatchesPlaceholder(): void
	{
		$env = self::baseEnv();
		$env['AUTHORIZATION_TOKEN_KEY'] = EnvironmentValidator::PlaceholderToken;

		$validator = new EnvironmentValidator($env);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('AUTHORIZATION_TOKEN_KEY is set to the .env.example placeholder');

		$validator->validate();
	}

	public function testFailsWhenAuthorizationTokenKeyIsShort(): void
	{
		$env = self::baseEnv();
		$env['AUTHORIZATION_TOKEN_KEY'] = 'short-but-not-placeholder';

		$validator = new EnvironmentValidator($env);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('AUTHORIZATION_TOKEN_KEY must be at least 32 characters');

		$validator->validate();
	}

	public function testProductionRejectsDefaultMysqlPassword(): void
	{
		$env = self::baseEnv();
		$env['APP_ENV'] = 'production';
		$env['MYSQL_PASSWORD'] = 'ukolio';

		$validator = new EnvironmentValidator($env);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('Refusing to boot with default or short secrets while APP_ENV=production');
		$this->expectExceptionMessageIsOrContains('MYSQL_PASSWORD');

		$validator->validate();
	}

	public function testProductionRejectsMultipleWeakSecrets(): void
	{
		$env = self::baseEnv();
		$env['APP_ENV'] = 'production';
		$env['MYSQL_PASSWORD'] = 'ukolio';
		$env['MYSQL_ROOT_PASSWORD'] = 'ukolio';
		$env['S3_ACCESS_KEY'] = 'minioadmin';
		$env['S3_SECRET_KEY'] = 'minioadmin';

		$validator = new EnvironmentValidator($env);

		try {
			$validator->validate();
			self::fail('Expected RuntimeException');
		} catch (RuntimeException $e) {
			self::assertStringContainsString('MYSQL_PASSWORD', $e->getMessage());
			self::assertStringContainsString('MYSQL_ROOT_PASSWORD', $e->getMessage());
			self::assertStringContainsString('S3_ACCESS_KEY', $e->getMessage());
			self::assertStringContainsString('S3_SECRET_KEY', $e->getMessage());
		}
	}

	public function testProductionRejectsDefaultRedisPassword(): void
	{
		$env = self::baseEnv();
		$env['APP_ENV'] = 'production';
		$env['REDIS_PASSWORD'] = 'ukolio';

		$validator = new EnvironmentValidator($env);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('REDIS_PASSWORD');

		$validator->validate();
	}

	public function testProductionRejectsDefaultRabbitmqPassword(): void
	{
		$env = self::baseEnv();
		$env['APP_ENV'] = 'production';
		$env['RABBITMQ_PASSWORD'] = 'ukolio';

		$validator = new EnvironmentValidator($env);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('RABBITMQ_PASSWORD');

		$validator->validate();
	}

	public function testProductionRejectsPlaceholderMeiliMasterKey(): void
	{
		$env = self::baseEnv();
		$env['APP_ENV'] = 'production';
		$env['MEILI_MASTER_KEY'] = EnvironmentValidator::PlaceholderToken;

		$validator = new EnvironmentValidator($env);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('MEILI_MASTER_KEY');

		$validator->validate();
	}

	public function testProductionRejectsPlaceholderMercureKeys(): void
	{
		$env = self::baseEnv();
		$env['APP_ENV'] = 'production';
		$env['MERCURE_PUBLISHER_JWT_KEY'] = EnvironmentValidator::PlaceholderToken;
		$env['MERCURE_SUBSCRIBER_JWT_KEY'] = EnvironmentValidator::PlaceholderToken;

		$validator = new EnvironmentValidator($env);

		try {
			$validator->validate();
			self::fail('Expected RuntimeException');
		} catch (RuntimeException $e) {
			self::assertStringContainsString('MERCURE_PUBLISHER_JWT_KEY', $e->getMessage());
			self::assertStringContainsString('MERCURE_SUBSCRIBER_JWT_KEY', $e->getMessage());
		}
	}

	public function testProductionRejectsShortSecret(): void
	{
		$env = self::baseEnv();
		$env['APP_ENV'] = 'production';
		$env['MYSQL_PASSWORD'] = 'short';

		$validator = new EnvironmentValidator($env);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('MYSQL_PASSWORD');

		$validator->validate();
	}

	public function testProductionAcceptsRotatedSecrets(): void
	{
		$this->expectNotToPerformAssertions();

		$env = self::baseEnv();
		$env['APP_ENV'] = 'production';

		$validator = new EnvironmentValidator($env);
		$validator->validate();
	}

	public function testProductionRequiresMysqlRootPasswordToBeSet(): void
	{
		$env = self::baseEnv();
		$env['APP_ENV'] = 'production';
		$env['MYSQL_ROOT_PASSWORD'] = '';

		$validator = new EnvironmentValidator($env);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('MYSQL_ROOT_PASSWORD');

		$validator->validate();
	}

	public function testDevelopmentSkipsProductionSecretCheck(): void
	{
		$this->expectNotToPerformAssertions();

		$env = self::baseEnv();
		$env['MYSQL_PASSWORD'] = 'ukolio';
		$env['MYSQL_ROOT_PASSWORD'] = 'ukolio';
		$env['S3_ACCESS_KEY'] = 'minioadmin';
		$env['S3_SECRET_KEY'] = 'minioadmin';
		$env['BACKEND_CORS_ALLOWED_ORIGIN'] = '*';

		$validator = new EnvironmentValidator($env);
		$validator->validate();
	}

	public function testProductionRejectsWildcardCorsOrigin(): void
	{
		$env = self::baseEnv();
		$env['APP_ENV'] = 'production';
		$env['BACKEND_CORS_ALLOWED_ORIGIN'] = '*';

		$validator = new EnvironmentValidator($env);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('BACKEND_CORS_ALLOWED_ORIGIN must not include `*` when APP_ENV=production');

		$validator->validate();
	}

	public function testProductionRejectsWildcardWithinList(): void
	{
		$env = self::baseEnv();
		$env['APP_ENV'] = 'production';
		$env['BACKEND_CORS_ALLOWED_ORIGIN'] = 'https://app.example.com, *';

		$validator = new EnvironmentValidator($env);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('BACKEND_CORS_ALLOWED_ORIGIN must not include `*`');

		$validator->validate();
	}

	public function testProductionRejectsEmptyCorsOrigin(): void
	{
		$env = self::baseEnv();
		$env['APP_ENV'] = 'production';
		$env['BACKEND_CORS_ALLOWED_ORIGIN'] = '';

		$validator = new EnvironmentValidator($env);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('BACKEND_CORS_ALLOWED_ORIGIN must list at least one origin');

		$validator->validate();
	}

	public function testProductionAcceptsExplicitOriginList(): void
	{
		$this->expectNotToPerformAssertions();

		$env = self::baseEnv();
		$env['APP_ENV'] = 'production';
		$env['BACKEND_CORS_ALLOWED_ORIGIN'] = 'https://app.example.com, https://staging.example.com';

		$validator = new EnvironmentValidator($env);
		$validator->validate();
	}
}
