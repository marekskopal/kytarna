<?php

declare(strict_types=1);

namespace Ukolio\Tests\Middleware;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ukolio\Middleware\CorsMiddleware;
use Ukolio\Service\Cors\CorsPolicy;

#[CoversClass(CorsMiddleware::class)]
final class CorsMiddlewareTest extends TestCase
{
	public function testPreflightShortCircuitsWithCorsHeaders(): void
	{
		$middleware = new CorsMiddleware(CorsPolicy::fromEnvValue('https://app.example.com'));

		$request = (new ServerRequest())
			->withMethod('OPTIONS')
			->withHeader('Origin', 'https://app.example.com');

		$handler = $this->failingHandler();

		$response = $middleware->process($request, $handler);

		self::assertSame(204, $response->getStatusCode());
		self::assertSame('https://app.example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
		self::assertSame('Origin', $response->getHeaderLine('Vary'));
		self::assertNotSame('', $response->getHeaderLine('Access-Control-Allow-Headers'));
		self::assertNotSame('', $response->getHeaderLine('Access-Control-Allow-Methods'));
	}

	public function testForwardingHandlerForGetRequest(): void
	{
		$middleware = new CorsMiddleware(CorsPolicy::fromEnvValue('https://app.example.com'));

		$request = (new ServerRequest())
			->withMethod('GET')
			->withHeader('Origin', 'https://app.example.com');

		$handler = new class implements RequestHandlerInterface {
			public function handle(ServerRequestInterface $request): ResponseInterface
			{
				return new TextResponse('ok', 200);
			}
		};

		$response = $middleware->process($request, $handler);

		self::assertSame(200, $response->getStatusCode());
		self::assertSame('ok', (string) $response->getBody());
		self::assertSame('https://app.example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
	}

	public function testOriginNotInAllowlistOmitsAllowOriginHeader(): void
	{
		$middleware = new CorsMiddleware(CorsPolicy::fromEnvValue('https://app.example.com'));

		$request = (new ServerRequest())
			->withMethod('GET')
			->withHeader('Origin', 'https://evil.example.com');

		$handler = new class implements RequestHandlerInterface {
			public function handle(ServerRequestInterface $request): ResponseInterface
			{
				return new Response();
			}
		};

		$response = $middleware->process($request, $handler);

		self::assertSame('', $response->getHeaderLine('Access-Control-Allow-Origin'));
	}

	public function testWildcardPolicyEmitsStarWithoutVary(): void
	{
		$middleware = new CorsMiddleware(CorsPolicy::fromEnvValue('*'));

		$request = (new ServerRequest())->withMethod('OPTIONS');

		$response = $middleware->process($request, $this->failingHandler());

		self::assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
		self::assertSame('', $response->getHeaderLine('Vary'));
	}

	private function failingHandler(): RequestHandlerInterface
	{
		return new class implements RequestHandlerInterface {
			public function handle(ServerRequestInterface $request): ResponseInterface
			{
				throw new \LogicException('Preflight must not reach the inner handler');
			}
		};
	}
}
