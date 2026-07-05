<?php

declare(strict_types=1);

namespace Ukolio\OAuth;

use Ukolio\Model\Entity\OAuthAuthorization;

interface AuthorizationServiceInterface
{
	public function createAuthorizationCode(
		string $clientId,
		int $userId,
		string $codeChallenge,
		string $codeChallengeMethod,
		string $redirectUri,
	): string;

	public function exchangeCode(string $code, string $codeVerifier, string $clientId, string $redirectUri): OAuthTokenPair;

	public function refreshToken(string $refreshToken, string $clientId): OAuthTokenPair;

	public function validateAccessToken(string $accessToken): OAuthAuthorization;
}
