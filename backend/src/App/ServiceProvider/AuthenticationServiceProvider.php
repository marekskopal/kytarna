<?php

declare(strict_types=1);

namespace Ukolio\App\ServiceProvider;

use League\Container\ServiceProvider\AbstractServiceProvider;
use Ukolio\Service\Authentication\AuthenticationService;
use Ukolio\Service\Authentication\AuthenticationServiceInterface;
use Ukolio\Service\Authentication\GoogleAuthService;
use Ukolio\Service\Authentication\GoogleAuthServiceInterface;
use Ukolio\Service\Authentication\RateLimitConfig;

final class AuthenticationServiceProvider extends AbstractServiceProvider
{
	public function provides(string $id): bool
	{
		return in_array($id, [
			AuthenticationServiceInterface::class,
			GoogleAuthServiceInterface::class,
			RateLimitConfig::class,
		], true);
	}

	public function register(): void
	{
		$container = $this->getContainer();

		// LoginAttemptService has only autowireable deps — leave it unregistered so the
		// ReflectionContainer delegate constructs it on demand.
		$container->add(RateLimitConfig::class, static fn (): RateLimitConfig => RateLimitConfig::fromEnv());
		$container->add(AuthenticationServiceInterface::class, AuthenticationService::class);
		$container->add(GoogleAuthServiceInterface::class, GoogleAuthService::class);
	}
}
