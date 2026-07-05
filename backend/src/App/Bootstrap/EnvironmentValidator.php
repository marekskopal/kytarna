<?php

declare(strict_types=1);

namespace Ukolio\App\Bootstrap;

use RuntimeException;
use Ukolio\Service\Cors\CorsPolicy;

final readonly class EnvironmentValidator
{
	public const string PlaceholderToken = 'replace-with-32-char-random-hex-key-here';
	public const string ProductionAppEnv = 'production';
	public const int AuthorizationTokenKeyMinLength = 32;
	public const int ProductionSecretMinLength = 16;

	private const array RequiredEnvVars = [
		'AUTHORIZATION_TOKEN_KEY',
		'MYSQL_HOST',
		'MYSQL_DATABASE',
		'MYSQL_USER',
		'MYSQL_PASSWORD',
		'S3_BUCKET',
		'S3_ACCESS_KEY',
		'S3_SECRET_KEY',
		'REDIS_HOST',
		'REDIS_PORT',
		'REDIS_PASSWORD',
		'MEMCACHED_HOST',
		'MEMCACHED_PORT',
	];

	private const array ProductionDefaultSecrets = [
		'MYSQL_PASSWORD' => 'ukolio',
		'MYSQL_ROOT_PASSWORD' => 'ukolio',
		'S3_ACCESS_KEY' => 'minioadmin',
		'S3_SECRET_KEY' => 'minioadmin',
		'REDIS_PASSWORD' => 'ukolio',
		'RABBITMQ_PASSWORD' => 'ukolio',
		'MEILI_MASTER_KEY' => self::PlaceholderToken,
		'MERCURE_PUBLISHER_JWT_KEY' => self::PlaceholderToken,
		'MERCURE_SUBSCRIBER_JWT_KEY' => self::PlaceholderToken,
	];

	/** @param array<string, string> $env */
	public function __construct(private array $env)
	{
	}

	public static function fromGlobals(): self
	{
		$names = array_unique(array_merge(
			self::RequiredEnvVars,
			array_keys(self::ProductionDefaultSecrets),
			['APP_ENV', 'BACKEND_CORS_ALLOWED_ORIGIN'],
		));

		$env = [];
		foreach ($names as $name) {
			$value = getenv($name);
			$env[$name] = $value === false ? '' : $value;
		}

		return new self($env);
	}

	public function validate(): void
	{
		$this->assertRequiredPresent();
		$this->assertAuthorizationTokenKeyStrong();

		if (($this->env['APP_ENV'] ?? '') !== self::ProductionAppEnv) {
			return;
		}

		$this->assertProductionSecretsStrong();
		$this->assertProductionCorsOriginStrict();
	}

	private function assertRequiredPresent(): void
	{
		$missing = [];
		foreach (self::RequiredEnvVars as $name) {
			if (($this->env[$name] ?? '') === '') {
				$missing[] = $name;
			}
		}

		if ($missing !== []) {
			throw new RuntimeException('Required environment variables are not set: ' . implode(', ', $missing));
		}
	}

	private function assertAuthorizationTokenKeyStrong(): void
	{
		$key = $this->env['AUTHORIZATION_TOKEN_KEY'] ?? '';

		if ($key === self::PlaceholderToken) {
			throw new RuntimeException(
				'AUTHORIZATION_TOKEN_KEY is set to the .env.example placeholder. '
				. 'Generate a real secret with `openssl rand -hex 32`.',
			);
		}

		if (strlen($key) < self::AuthorizationTokenKeyMinLength) {
			throw new RuntimeException(sprintf(
				'AUTHORIZATION_TOKEN_KEY must be at least %d characters. Generate one with `openssl rand -hex 32`.',
				self::AuthorizationTokenKeyMinLength,
			));
		}
	}

	private function assertProductionSecretsStrong(): void
	{
		$weak = [];
		foreach (self::ProductionDefaultSecrets as $name => $devDefault) {
			$value = $this->env[$name] ?? '';
			if ($value === '' || $value === $devDefault || strlen($value) < self::ProductionSecretMinLength) {
				$weak[] = $name;
			}
		}

		if ($weak !== []) {
			throw new RuntimeException(sprintf(
				'Refusing to boot with default or short secrets while APP_ENV=production: %s. '
				. 'Set strong values (at least %d characters; e.g. `openssl rand -hex 32`).',
				implode(', ', $weak),
				self::ProductionSecretMinLength,
			));
		}
	}

	private function assertProductionCorsOriginStrict(): void
	{
		$policy = CorsPolicy::fromEnvValue($this->env['BACKEND_CORS_ALLOWED_ORIGIN'] ?? '');

		if ($policy->origins() === []) {
			throw new RuntimeException(
				'BACKEND_CORS_ALLOWED_ORIGIN must list at least one origin when APP_ENV=production. '
				. 'Set it to a space- or comma-separated list of allowed origins (e.g. `https://app.example.com`).',
			);
		}

		if ($policy->allowsAnyOrigin()) {
			throw new RuntimeException(
				'BACKEND_CORS_ALLOWED_ORIGIN must not include `*` when APP_ENV=production. '
				. 'Replace it with an explicit list of origins (e.g. `https://app.example.com`).',
			);
		}
	}
}
