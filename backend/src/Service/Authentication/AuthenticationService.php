<?php

declare(strict_types=1);

namespace Kytario\Service\Authentication;

use Firebase\JWT\JWT;
use Kytario\Dto\AuthenticationDto;
use Kytario\Dto\CredentialsDto;
use Kytario\Model\Entity\User;
use Kytario\Service\Authentication\Exception\AuthenticationException;
use Kytario\Service\Provider\UserProviderInterface;

final readonly class AuthenticationService implements AuthenticationServiceInterface
{
	private const int AccessTokenExpiration = 3600;
	private const int RefreshTokenExpiration = 604800;

	/**
	 * Bcrypt hash of a random throwaway string. Verified against when the account
	 * does not exist so absent and present users take comparable time (timing oracle).
	 */
	private const string DummyPasswordHash = '$2y$12$bM7D3SRpgIxPKSOrodU8fufxpIw6wuJjlnOyH66eJdDqlexKtgeMa';

	public function __construct(private UserProviderInterface $userProvider, private LoginAttemptService $loginAttempts)
	{
	}

	public function authenticate(CredentialsDto $credentials): AuthenticationDto
	{
		$user = $this->userProvider->getUserByEmail($credentials->email);
		if ($user === null) {
			password_verify($credentials->password, self::DummyPasswordHash);

			throw new AuthenticationException('Invalid credentials.');
		}

		$this->loginAttempts->assertNotLocked($user);

		if ($user->password === null || !password_verify($credentials->password, $user->password)) {
			$this->loginAttempts->recordFailure($user);
			// If this failure just tripped the lock, surface that to the caller as 429.
			$this->loginAttempts->assertNotLocked($user);

			throw new AuthenticationException('Invalid credentials.');
		}

		$this->loginAttempts->recordSuccess($user);

		return $this->createAuthentication($user);
	}

	public function createAuthentication(User $user): AuthenticationDto
	{
		$accessTokenExpiration = time() + self::AccessTokenExpiration;
		$refreshTokenExpiration = time() + self::RefreshTokenExpiration;

		return new AuthenticationDto(
			accessToken: $this->createToken([
				'id' => $user->id,
				'tv' => $user->tokenVersion,
				'type' => self::TokenTypeAccess,
				'exp' => $accessTokenExpiration,
			]),
			refreshToken: $this->createToken([
				'id' => $user->id,
				'tv' => $user->tokenVersion,
				'type' => self::TokenTypeRefresh,
				'exp' => $refreshTokenExpiration,
			]),
			userId: $user->id,
		);
	}

	/** @param array<string,mixed> $claims */
	private function createToken(array $claims): string
	{
		$key = (string) getenv('AUTHORIZATION_TOKEN_KEY');

		return JWT::encode($claims, $key, self::TokenAlgorithm);
	}
}
