<?php

declare(strict_types=1);

namespace Ukolio\OAuth;

use DateTimeImmutable;
use MarekSkopal\ORM\Database\DatabaseInterface;
use RuntimeException;
use Ukolio\Model\Entity\OAuthAuthorization;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\OAuthAuthorizationRepository;
use Ukolio\Service\Provider\UserProviderInterface;

final readonly class AuthorizationService implements AuthorizationServiceInterface
{
	private const int AccessTokenLifetime = 3600;

	private const int RefreshTokenLifetime = 2592000;

	private const int CodeLifetime = 60;

	public function __construct(
		private OAuthAuthorizationRepository $oAuthAuthorizationRepository,
		private PkceVerifier $pkceVerifier,
		private ClientServiceInterface $clientService,
		private UserProviderInterface $userProvider,
		private DatabaseInterface $database,
	) {
	}

	public function createAuthorizationCode(
		string $clientId,
		int $userId,
		string $codeChallenge,
		string $codeChallengeMethod,
		string $redirectUri,
	): string {
		$client = $this->clientService->findByClientId($clientId);
		if ($client === null) {
			throw new RuntimeException('Unknown client');
		}

		$user = $this->userProvider->getUser($userId);
		if ($user === null) {
			throw new RuntimeException('Unknown user');
		}

		$code = bin2hex(random_bytes(32));

		$now = new DateTimeImmutable();
		$authorization = new OAuthAuthorization(
			clientId: $clientId,
			user: $user,
			authorizationCodeHash: hash('sha256', $code),
			codeChallenge: $codeChallenge,
			codeChallengeMethod: $codeChallengeMethod,
			redirectUri: $redirectUri,
			codeExpires: time() + self::CodeLifetime,
			familyId: bin2hex(random_bytes(16)),
		);
		$authorization->createdAt = $now;
		$authorization->updatedAt = $now;

		$this->oAuthAuthorizationRepository->persist($authorization);

		return $code;
	}

	public function exchangeCode(string $code, string $codeVerifier, string $clientId, string $redirectUri): OAuthTokenPair
	{
		$codeHash = hash('sha256', $code);

		$authorization = $this->oAuthAuthorizationRepository->findByAuthorizationCodeHash($codeHash);
		if ($authorization === null) {
			throw new RuntimeException('Invalid authorization code');
		}

		if ($authorization->codeExpires !== null && $authorization->codeExpires < time()) {
			throw new RuntimeException('Authorization code has expired');
		}

		if ($authorization->clientId !== $clientId) {
			throw new RuntimeException('Client ID mismatch');
		}

		if ($authorization->redirectUri !== $redirectUri) {
			throw new RuntimeException('Redirect URI mismatch');
		}

		// Atomic single-use consume (OAuth 2.1): only one concurrent exchange can flip
		// revoked 0→1; every other request with the same code — including replays —
		// fails here before any token is minted. The consume happens before the PKCE
		// check so a code allows exactly one verifier attempt. Replaying a consumed
		// code revokes every token descended from it (RFC 9700).
		if ($authorization->revoked || !$this->consume('authorization_code_hash', $codeHash)) {
			$this->revokeFamily($authorization->familyId);

			throw new RuntimeException('Authorization code has already been used');
		}

		if ($authorization->codeChallenge === null || !$this->pkceVerifier->verify($codeVerifier, $authorization->codeChallenge)) {
			throw new RuntimeException('PKCE verification failed');
		}

		return $this->issueTokenPair($clientId, $authorization->user, $authorization->familyId);
	}

	/** Flips `revoked` 0→1 for the row holding the given token hash; true only for the single winning request. */
	private function consume(string $hashColumn, string $hash): bool
	{
		$statement = $this->database->getPdo()->prepare(
			sprintf('UPDATE oauth_authorizations SET revoked = 1, updated_at = NOW() WHERE %s = :hash AND revoked = 0', $hashColumn),
		);
		if ($statement === false) {
			throw new RuntimeException('Failed to prepare the token consume statement');
		}

		$statement->execute(['hash' => $hash]);

		return $statement->rowCount() === 1;
	}

	/** Revokes every live token in a lineage; a no-op for legacy rows without a family id. */
	private function revokeFamily(?string $familyId): void
	{
		if ($familyId === null) {
			return;
		}

		$statement = $this->database->getPdo()->prepare(
			'UPDATE oauth_authorizations SET revoked = 1, updated_at = NOW() WHERE family_id = :familyId AND revoked = 0',
		);
		if ($statement === false) {
			throw new RuntimeException('Failed to prepare the family revocation statement');
		}

		$statement->execute(['familyId' => $familyId]);
	}

	public function refreshToken(string $refreshToken, string $clientId): OAuthTokenPair
	{
		$refreshTokenHash = hash('sha256', $refreshToken);

		$authorization = $this->oAuthAuthorizationRepository->findByRefreshTokenHash($refreshTokenHash);
		if ($authorization === null) {
			throw new RuntimeException('Invalid refresh token');
		}

		if ($authorization->refreshTokenExpires !== null && $authorization->refreshTokenExpires < time()) {
			throw new RuntimeException('Refresh token has expired');
		}

		if ($authorization->clientId !== $clientId) {
			throw new RuntimeException('Client ID mismatch');
		}

		// Replay of an already-rotated refresh token means either theft or a lost
		// race; both revoke the whole family (RFC 9700), killing the live descendant
		// a thief may hold. The rotation itself is the same atomic 0→1 flip as the
		// code exchange, so only one concurrent refresh can win.
		if ($authorization->revoked || !$this->consume('refresh_token_hash', $refreshTokenHash)) {
			$this->revokeFamily($authorization->familyId);

			throw new RuntimeException('Refresh token has been revoked');
		}

		return $this->issueTokenPair($clientId, $authorization->user, $authorization->familyId);
	}

	public function validateAccessToken(string $accessToken): OAuthAuthorization
	{
		$accessTokenHash = hash('sha256', $accessToken);

		$authorization = $this->oAuthAuthorizationRepository->findByAccessTokenHash($accessTokenHash);
		if ($authorization === null) {
			throw new RuntimeException('Invalid access token');
		}

		if ($authorization->revoked) {
			throw new RuntimeException('Access token has been revoked');
		}

		if ($authorization->accessTokenExpires !== null && $authorization->accessTokenExpires < time()) {
			throw new RuntimeException('Access token has expired');
		}

		return $authorization;
	}

	private function issueTokenPair(string $clientId, User $user, ?string $familyId): OAuthTokenPair
	{
		$accessToken = bin2hex(random_bytes(32));
		$refreshToken = bin2hex(random_bytes(32));

		$now = new DateTimeImmutable();
		$authorization = new OAuthAuthorization(
			clientId: $clientId,
			user: $user,
			accessTokenHash: hash('sha256', $accessToken),
			refreshTokenHash: hash('sha256', $refreshToken),
			accessTokenExpires: time() + self::AccessTokenLifetime,
			refreshTokenExpires: time() + self::RefreshTokenLifetime,
			familyId: $familyId,
		);
		$authorization->createdAt = $now;
		$authorization->updatedAt = $now;

		$this->oAuthAuthorizationRepository->persist($authorization);

		return new OAuthTokenPair(accessToken: $accessToken, refreshToken: $refreshToken, expiresIn: self::AccessTokenLifetime);
	}
}
