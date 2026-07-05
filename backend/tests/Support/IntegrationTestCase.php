<?php

declare(strict_types=1);

namespace Ukolio\Tests\Support;

use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ukolio\App\Application;
use Ukolio\Model\Entity\User;

abstract class IntegrationTestCase extends TestCase
{
	protected Application $app;

	protected ContainerInterface $container;

	protected RequestHandlerInterface $handler;

	protected function setUp(): void
	{
		parent::setUp();

		AppHarness::resetState();
		Fixture::reset();

		$this->app = AppHarness::app();
		$this->container = $this->app->container;
		$this->handler = $this->app->handler;
	}

	/**
	 * Dispatch a JSON request through the production router + middleware stack.
	 *
	 * @param array<string, mixed>|null $body
	 */
	protected function request(
		string $method,
		string $path,
		?array $body = null,
		?User $authenticatedAs = null,
		?string $bearerToken = null,
	): ResponseInterface {
		return $this->handler->handle($this->buildRequest($method, $path, $body, $authenticatedAs, $bearerToken));
	}

	/** @param array<string, mixed>|null $body */
	protected function buildRequest(
		string $method,
		string $path,
		?array $body = null,
		?User $authenticatedAs = null,
		?string $bearerToken = null,
	): ServerRequestInterface {
		$request = new ServerRequest([], [], $path, $method);

		// Diactoros doesn't auto-populate query params from the URI string;
		// parse them explicitly so controllers can read $request->getQueryParams().
		$query = $request->getUri()->getQuery();
		if ($query !== '') {
			parse_str($query, $parsed);
			$request = $request->withQueryParams($parsed);
		}

		if ($body !== null) {
			$encoded = Json::encode($body);
			$stream = new Stream('php://temp', 'r+');
			$stream->write($encoded);
			$stream->rewind();
			$request = $request->withBody($stream)->withHeader('Content-Type', 'application/json');
		}

		if ($bearerToken !== null) {
			$request = $request->withHeader('Authorization', 'Bearer ' . $bearerToken);
		} elseif ($authenticatedAs !== null) {
			$request = $request->withHeader('Authorization', 'Bearer ' . Fixture::accessTokenFor($authenticatedAs));
		}

		return $request;
	}

	/** @return array<string, mixed> */
	protected function jsonBody(ResponseInterface $response): array
	{
		$body = (string) $response->getBody();
		/** @var array<string, mixed> $decoded */
		$decoded = Json::decode($body, forceArrays: true);
		return $decoded;
	}

	/** @return list<array<string, mixed>> */
	protected function jsonList(ResponseInterface $response): array
	{
		$body = (string) $response->getBody();
		/** @var list<array<string, mixed>> $decoded */
		$decoded = Json::decode($body, forceArrays: true);
		return $decoded;
	}

	/**
	 * Narrow a mixed value coming out of a JSON body to an int.
	 */
	protected static function intField(mixed $value): int
	{
		self::assertIsInt($value);
		return $value;
	}

	/**
	 * Narrow a mixed value coming out of a JSON body to a string.
	 */
	protected static function stringField(mixed $value): string
	{
		self::assertIsString($value);
		return $value;
	}
}
