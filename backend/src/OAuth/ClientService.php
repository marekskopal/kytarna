<?php

declare(strict_types=1);

namespace Kytario\OAuth;

use DateTimeImmutable;
use Kytario\Model\Entity\OAuthClient;
use Kytario\Model\Repository\OAuthClientRepository;
use MarekSkopal\ORM\Database\DatabaseInterface;
use RuntimeException;
use const JSON_THROW_ON_ERROR;

final readonly class ClientService implements ClientServiceInterface
{
	public const int MaxRedirectUris = 10;
	public const int MaxClientNameLength = 100;
	public const int MaxRedirectUriLength = 2000;

	/** Anonymous registrations that never completed an authorization are purged after this long. */
	private const string AnonymousClientMaxAge = '30 days';

	public function __construct(private OAuthClientRepository $oAuthClientRepository, private DatabaseInterface $database)
	{
	}

	public function findByClientId(string $clientId): ?OAuthClient
	{
		return $this->oAuthClientRepository->findByClientId($clientId);
	}

	public function validateRedirectUri(string $clientId, string $redirectUri): bool
	{
		$client = $this->oAuthClientRepository->findByClientId($clientId);
		if ($client === null) {
			return false;
		}

		/** @var list<string> $allowedUris */
		$allowedUris = json_decode($client->redirectUris, true, 2, JSON_THROW_ON_ERROR);

		foreach ($allowedUris as $allowedUri) {
			if ($this->matchesRedirectUri($allowedUri, $redirectUri)) {
				return true;
			}
		}

		return false;
	}

	/** @param list<string> $redirectUris */
	public function registerClient(string $clientName, array $redirectUris): OAuthClient
	{
		if (count($redirectUris) > self::MaxRedirectUris) {
			throw new RuntimeException(sprintf('Too many redirect_uris (max %d)', self::MaxRedirectUris), 400);
		}

		foreach ($redirectUris as $redirectUri) {
			if (strlen($redirectUri) > self::MaxRedirectUriLength) {
				throw new RuntimeException(sprintf('redirect_uri is too long (max %d characters)', self::MaxRedirectUriLength), 400);
			}

			self::assertSafeRedirectUri($redirectUri);
		}

		$this->garbageCollectAnonymousClients();

		$now = new DateTimeImmutable();
		$client = new OAuthClient(
			clientId: bin2hex(random_bytes(16)),
			clientName: self::sanitizeClientName($clientName),
			redirectUris: json_encode($redirectUris, JSON_THROW_ON_ERROR),
			user: null,
		);
		$client->createdAt = $now;
		$client->updatedAt = $now;

		$this->oAuthClientRepository->persist($client);

		return $client;
	}

	/**
	 * The name is attacker-controlled (open dynamic registration) and rendered in every
	 * admin's MCP-clients list: strip control/invisible characters and cap the length so
	 * it cannot smuggle formatting or fill the page.
	 */
	private static function sanitizeClientName(string $clientName): string
	{
		$clientName = trim((string) preg_replace('/\p{C}+/u', '', $clientName));
		if ($clientName === '') {
			return 'MCP Client';
		}

		return mb_substr($clientName, 0, self::MaxClientNameLength);
	}

	/**
	 * Registration is open, so the redirect_uri is fully attacker-controlled and later fed to a
	 * `window.location` navigation on approval. Only `https` (or `http`/`https` for loopback dev
	 * clients) is allowed — a `javascript:`/`data:` scheme would execute in the app's own origin,
	 * where the session JWTs live, once a victim approved the client.
	 */
	private static function assertSafeRedirectUri(string $redirectUri): void
	{
		$parsed = parse_url($redirectUri);
		if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
			throw new RuntimeException('redirect_uri must be an absolute http(s) URL', 400);
		}

		$scheme = strtolower($parsed['scheme']);
		$host = strtolower($parsed['host']);
		$isLoopback = in_array($host, ['localhost', '127.0.0.1', '::1'], true);

		if ($scheme === 'https' || ($scheme === 'http' && $isLoopback)) {
			return;
		}

		throw new RuntimeException('redirect_uri must use https (http is allowed only for loopback)', 400);
	}

	/** Open registration must not grow the table unboundedly: drop stale registrations no user ever approved. */
	private function garbageCollectAnonymousClients(): void
	{
		$statement = $this->database->getPdo()->prepare(
			'DELETE c FROM oauth_clients c'
			. ' LEFT JOIN oauth_authorizations a ON a.client_id = c.client_id'
			. ' WHERE c.user_id IS NULL AND a.id IS NULL AND c.created_at < :cutoff',
		);
		if ($statement === false) {
			throw new RuntimeException('Failed to prepare the client GC statement');
		}

		$statement->execute(['cutoff' => new DateTimeImmutable('-' . self::AnonymousClientMaxAge)->format('Y-m-d H:i:s')]);
	}

	private function matchesRedirectUri(string $allowedUri, string $requestedUri): bool
	{
		$allowedParsed = parse_url($allowedUri);
		$requestedParsed = parse_url($requestedUri);

		if ($allowedParsed === false || $requestedParsed === false) {
			return false;
		}

		$allowedHost = $allowedParsed['host'] ?? '';
		$requestedHost = $requestedParsed['host'] ?? '';

		// OAuth 2.1 permits localhost with any port to match
		if (
			in_array($allowedHost, ['localhost', '127.0.0.1', '::1'], true)
			&& $allowedHost === $requestedHost
			&& ($allowedParsed['scheme'] ?? '') === ($requestedParsed['scheme'] ?? '')
			&& ($allowedParsed['path'] ?? '/') === ($requestedParsed['path'] ?? '/')
		) {
			return true;
		}

		return $allowedUri === $requestedUri;
	}
}
