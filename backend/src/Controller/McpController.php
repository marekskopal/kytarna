<?php

declare(strict_types=1);

namespace Ukolio\Controller;

use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\Http\Middleware\ProtocolVersionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Predis\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Mcp\Server\UkolioServer;
use Ukolio\Mcp\Session\RedisSessionStore;
use Ukolio\OAuth\AuthorizationServiceInterface;
use Ukolio\OAuth\ClientServiceInterface;
use Ukolio\Response\ErrorResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Actor\ActorContextInterface;

final readonly class McpController
{
	public function __construct(
		private AuthorizationServiceInterface $authorizationService,
		private ClientServiceInterface $clientService,
		private McpUserContextInterface $userContext,
		private ActorContextInterface $actorContext,
		private UkolioServer $server,
		private ClientInterface $redis,
	) {
	}

	#[RouteGet(Routes::Mcp->value)]
	public function actionGetMcp(ServerRequestInterface $request): ResponseInterface
	{
		return $this->handleMcp($request);
	}

	#[RoutePost(Routes::Mcp->value)]
	public function actionPostMcp(ServerRequestInterface $request): ResponseInterface
	{
		return $this->handleMcp($request);
	}

	#[RouteDelete(Routes::Mcp->value)]
	public function actionDeleteMcp(ServerRequestInterface $request): ResponseInterface
	{
		return $this->handleMcp($request);
	}

	private function handleMcp(ServerRequestInterface $request): ResponseInterface
	{
		$token = $this->extractBearerToken($request);
		if ($token === null) {
			return $this->unauthorized($request, 'Missing or invalid Authorization header. Expected: Bearer <access_token>');
		}

		try {
			$authorization = $this->authorizationService->validateAccessToken($token);
		} catch (RuntimeException) {
			return $this->unauthorized($request, 'Invalid or expired access token.');
		}

		$this->userContext->setUser($authorization->user);

		$clientName = $authorization->clientId;
		$client = $this->clientService->findByClientId($authorization->clientId);
		if ($client !== null) {
			$clientName = $client->clientName;
		}
		$this->actorContext->setAgent($authorization->clientId, $clientName);

		$sessionStore = new RedisSessionStore($this->redis, $this->sessionTtl());
		$mcpServer = $this->server->build($sessionStore);
		$transport = new StreamableHttpTransport($request, middleware: [
			new CorsMiddleware(),
			new DnsRebindingProtectionMiddleware($this->allowedHosts()),
			new ProtocolVersionMiddleware(),
		]);

		return $mcpServer->run($transport);
	}

	private function extractBearerToken(ServerRequestInterface $request): ?string
	{
		$header = $request->getHeaderLine('Authorization');
		if ($header === '' || !str_starts_with($header, 'Bearer ')) {
			return null;
		}

		$token = substr($header, 7);

		return $token !== '' ? $token : null;
	}

	private function unauthorized(ServerRequestInterface $request, string $message): ErrorResponse
	{
		$scheme = $request->getHeaderLine('X-Forwarded-Proto');
		if ($scheme === '') {
			$scheme = $request->getUri()->getScheme();
		}
		$host = $request->getHeaderLine('X-Forwarded-Host');
		if ($host === '') {
			$host = $request->getHeaderLine('Host');
		}
		if ($host === '') {
			$host = $request->getUri()->getAuthority();
		}
		$baseUrl = $scheme . '://' . $host;

		return new ErrorResponse($message, 401, [
			'WWW-Authenticate' => 'Bearer resource_metadata="' . $baseUrl . Routes::OAuthResourceMetadata->value . '"',
		]);
	}

	/**
	 * Hosts permitted by the transport's DNS-rebinding protection. The default SDK allowlist is
	 * localhost-only, which 403s the public host in production; seed it from PROXY_HOST so deployed
	 * requests pass while keeping defense-in-depth (nginx already enforces `server_name`).
	 *
	 * @return list<string>
	 */
	private function allowedHosts(): array
	{
		$hosts = ['localhost', '127.0.0.1', '[::1]'];

		$proxyHost = getenv('PROXY_HOST');
		if ($proxyHost !== false && $proxyHost !== '') {
			$hosts[] = strtolower($proxyHost);
		}

		return $hosts;
	}

	private function sessionTtl(): int
	{
		$ttl = (int) getenv('MCP_SESSION_TTL');

		return $ttl > 0 ? $ttl : 86400;
	}
}
