<?php

declare(strict_types=1);

namespace Ukolio\Tests\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\Attributes\CoversClass;
use Ukolio\Middleware\AuthorizationMiddleware;
use Ukolio\Service\Authentication\AuthenticationServiceInterface;
use Ukolio\Service\Provider\UserProviderInterface;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(AuthorizationMiddleware::class)]
final class TokenRevocationTest extends IntegrationTestCase
{
	private function userProvider(): UserProviderInterface
	{
		$provider = $this->container->get(UserProviderInterface::class);
		assert($provider instanceof UserProviderInterface);

		return $provider;
	}

	private function authService(): AuthenticationServiceInterface
	{
		$service = $this->container->get(AuthenticationServiceInterface::class);
		assert($service instanceof AuthenticationServiceInterface);

		return $service;
	}

	public function testChangingPasswordRevokesOutstandingTokens(): void
	{
		$user = Fixture::createUser();
		$token = Fixture::accessTokenFor($user);

		// The token is valid while the user is still at token version 0.
		self::assertSame(200, $this->request('GET', '/api/current-user', bearerToken: $token)->getStatusCode());

		// Change the password through the real endpoint (Fixture users start with 'TestPass1!').
		// This bumps the token version, which must invalidate the token just used above.
		$changed = $this->request(
			'POST',
			'/api/current-user/password',
			body: ['currentPassword' => 'TestPass1!', 'newPassword' => 'BrandNewPass1!'],
			bearerToken: $token,
		);
		self::assertSame(200, $changed->getStatusCode());

		self::assertSame(401, $this->request('GET', '/api/current-user', bearerToken: $token)->getStatusCode());
	}

	public function testTokensIssuedAfterChangeRemainValid(): void
	{
		$user = Fixture::createUser();

		$updated = $this->userProvider()->updateUserPassword($user, 'BrandNewPass1!');
		$auth = $this->authService()->createAuthentication($updated);

		// The freshly minted token carries the new version claim and is accepted.
		self::assertSame(200, $this->request('GET', '/api/current-user', bearerToken: $auth->accessToken)->getStatusCode());

		$key = (string) getenv('AUTHORIZATION_TOKEN_KEY');
		$decoded = JWT::decode($auth->accessToken, new Key($key, AuthenticationServiceInterface::TokenAlgorithm));
		self::assertSame($updated->tokenVersion, $decoded->tv ?? null);
	}

	public function testLegacyTokenWithoutVersionClaimIsAcceptedAtVersionZero(): void
	{
		$user = Fixture::createUser();

		// Fixture::accessTokenFor issues a {id, exp} token with no `tv` claim, mirroring
		// tokens minted before this column existed; it must still work at version 0.
		$token = Fixture::accessTokenFor($user);

		self::assertSame(0, $user->tokenVersion);
		self::assertSame(200, $this->request('GET', '/api/current-user', bearerToken: $token)->getStatusCode());
	}
}
