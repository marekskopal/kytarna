<?php

declare(strict_types=1);

namespace Kytario\Tests\Controller;

use DateTimeImmutable;
use Kytario\Controller\OAuthController;
use Kytario\Model\Repository\OAuthClientRepository;
use Kytario\OAuth\ClientServiceInterface;
use Kytario\Tests\Support\AppHarness;
use Kytario\Tests\Support\Fixture;
use Kytario\Tests\Support\IntegrationTestCase;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(OAuthController::class)]
final class OAuthControllerTest extends IntegrationTestCase
{
	public function testFullPkceAuthorizeAndTokenHttpFlow(): void
	{
		$user = Fixture::createUser();

		// 1. Register a client.
		$register = $this->request(
			'POST',
			'/mcp/oauth/register',
			body: ['client_name' => 'Test Client', 'redirect_uris' => ['http://localhost/cb']],
		);
		self::assertSame(201, $register->getStatusCode());
		$clientId = self::stringField($this->jsonBody($register)['client_id']);

		// 2. Authorize as the user (PKCE S256 challenge).
		[$verifier, $challenge] = $this->pkcePair();

		$authorize = $this->request(
			'POST',
			'/mcp/oauth/authorize',
			body: [
				'clientId' => $clientId,
				'redirectUri' => 'http://localhost/cb',
				'codeChallenge' => $challenge,
				'codeChallengeMethod' => 'S256',
				'state' => 'state-token',
			],
			authenticatedAs: $user,
		);
		self::assertSame(200, $authorize->getStatusCode());
		$code = self::stringField($this->jsonBody($authorize)['code']);

		// 3. Exchange the code for tokens via the form-encoded token endpoint.
		$token = $this->postFormToken([
			'grant_type' => 'authorization_code',
			'code' => $code,
			'code_verifier' => $verifier,
			'client_id' => $clientId,
			'redirect_uri' => 'http://localhost/cb',
		]);

		self::assertSame(200, $token->getStatusCode());
		$tokenBody = $this->jsonBody($token);
		self::assertArrayHasKey('access_token', $tokenBody);
		self::assertArrayHasKey('refresh_token', $tokenBody);
		self::assertSame(3600, $tokenBody['expires_in']);
	}

	public function testTokenExchangeRejectsWrongPkceVerifier(): void
	{
		$user = Fixture::createUser();

		$register = $this->request(
			'POST',
			'/mcp/oauth/register',
			body: ['client_name' => 'Test Client', 'redirect_uris' => ['http://localhost/cb']],
		);
		$clientId = self::stringField($this->jsonBody($register)['client_id']);

		[, $challenge] = $this->pkcePair();

		$authorize = $this->request(
			'POST',
			'/mcp/oauth/authorize',
			body: [
				'clientId' => $clientId,
				'redirectUri' => 'http://localhost/cb',
				'codeChallenge' => $challenge,
				'codeChallengeMethod' => 'S256',
			],
			authenticatedAs: $user,
		);
		$code = self::stringField($this->jsonBody($authorize)['code']);

		$token = $this->postFormToken([
			'grant_type' => 'authorization_code',
			'code' => $code,
			'code_verifier' => 'wrong-verifier-value',
			'client_id' => $clientId,
			'redirect_uri' => 'http://localhost/cb',
		]);

		self::assertSame(400, $token->getStatusCode());
		$body = $this->jsonBody($token);
		self::assertSame('invalid_grant', $body['error']);
	}

	public function testAuthorizeRejectsForeignResourceIndicator(): void
	{
		$user = Fixture::createUser();

		$register = $this->request(
			'POST',
			'/mcp/oauth/register',
			body: ['client_name' => 'Test Client', 'redirect_uris' => ['http://localhost/cb']],
		);
		$clientId = self::stringField($this->jsonBody($register)['client_id']);

		[, $challenge] = $this->pkcePair();

		// A resource indicator naming a different server must be rejected (RFC 8707):
		// this AS only issues tokens for its own MCP resource.
		$authorize = $this->request(
			'POST',
			'/mcp/oauth/authorize',
			body: [
				'clientId' => $clientId,
				'redirectUri' => 'http://localhost/cb',
				'codeChallenge' => $challenge,
				'codeChallengeMethod' => 'S256',
				'resource' => 'https://attacker.example/mcp',
			],
			authenticatedAs: $user,
		);

		self::assertSame(400, $authorize->getStatusCode());
	}

	/** @return array{0:string,1:string} [verifier, S256 challenge] */
	private function pkcePair(): array
	{
		$verifier = bin2hex(random_bytes(32));
		$challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
		return [$verifier, $challenge];
	}

	public function testDiscoveryIgnoresSpoofedForwardedHost(): void
	{
		$request = $this->buildRequest('GET', '/.well-known/oauth-authorization-server/mcp')
			->withHeader('X-Forwarded-Host', 'attacker.example')
			->withHeader('X-Forwarded-Proto', 'https');

		$response = $this->handler->handle($request);

		self::assertSame(200, $response->getStatusCode());
		$body = $this->jsonBody($response);
		$issuer = self::stringField($body['issuer']);
		// PROXY_HOST is pinned to test.local in the test bootstrap; a spoofed
		// forwarded host must not leak into the advertised URLs.
		self::assertStringNotContainsString('attacker.example', $issuer);
		self::assertStringContainsString('test.local', $issuer);
		self::assertStringNotContainsString('attacker.example', (string) json_encode($body));
	}

	public function testRegisterCapsRedirectUriCount(): void
	{
		$uris = array_map(static fn (int $i): string => 'http://localhost/cb' . $i, range(1, 11));

		$register = $this->request(
			'POST',
			'/mcp/oauth/register',
			body: ['client_name' => 'Greedy Client', 'redirect_uris' => $uris],
		);

		self::assertSame(400, $register->getStatusCode());
	}

	public function testRegisterSanitizesAndClampsClientName(): void
	{
		$register = $this->request(
			'POST',
			'/mcp/oauth/register',
			body: [
				'client_name' => "  Sneaky\u{202E}\u{0000}\nName " . str_repeat('x', 300),
				'redirect_uris' => ['http://localhost/cb'],
			],
		);

		self::assertSame(201, $register->getStatusCode());
		$name = self::stringField($this->jsonBody($register)['client_name']);
		self::assertSame(100, mb_strlen($name));
		self::assertStringStartsWith('SneakyName', $name);
	}

	public function testStaleAnonymousClientsAreGarbageCollectedOnRegistration(): void
	{
		$clientService = $this->container->get(ClientServiceInterface::class);
		assert($clientService instanceof ClientServiceInterface);
		$stale = $clientService->registerClient('Stale', ['http://localhost/cb']);

		$repo = $this->container->get(OAuthClientRepository::class);
		assert($repo instanceof OAuthClientRepository);
		$stale->createdAt = new DateTimeImmutable('-60 days');
		$repo->persist($stale);

		$fresh = $clientService->registerClient('Fresh', ['http://localhost/cb']);

		AppHarness::app()->dbContext->getOrm()->getEntityCache()->clear();
		self::assertNull($clientService->findByClientId($stale->clientId));
		self::assertNotNull($clientService->findByClientId($fresh->clientId));
	}

	/**
	 * The token endpoint reads form-encoded bodies via getParsedBody(), so the JSON-based
	 * IntegrationTestCase::request helper would not populate the fields. Bypass it.
	 *
	 * @param array<string, string> $form
	 */
	private function postFormToken(array $form): ResponseInterface
	{
		$request = new ServerRequest([], [], '/mcp/oauth/token', 'POST');
		$request = $request
			->withHeader('Content-Type', 'application/x-www-form-urlencoded')
			->withParsedBody($form);

		return $this->handler->handle($request);
	}
}
