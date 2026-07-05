<?php

declare(strict_types=1);

namespace Ukolio\Tests\OAuth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Ukolio\OAuth\ClientService;
use Ukolio\OAuth\ClientServiceInterface;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(ClientService::class)]
final class ClientServiceTest extends IntegrationTestCase
{
	private function clientService(): ClientServiceInterface
	{
		$clientService = $this->container->get(ClientServiceInterface::class);
		assert($clientService instanceof ClientServiceInterface);

		return $clientService;
	}

	/** @return list<array{string}> */
	public static function unsafeRedirectUriProvider(): array
	{
		return [
			['javascript:alert(document.cookie)'],
			['data:text/html,<script>alert(1)</script>'],
			['ftp://example.com/cb'],
			// plain http is only allowed for loopback
			['http://example.com/cb'],
			['/relative/path'],
			['not a url'],
		];
	}

	#[DataProvider('unsafeRedirectUriProvider')]
	public function testRegisterClientRejectsUnsafeRedirectUri(string $redirectUri): void
	{
		$this->expectException(RuntimeException::class);

		$this->clientService()->registerClient('Malicious Client', [$redirectUri]);
	}

	/** @return list<array{string}> */
	public static function safeRedirectUriProvider(): array
	{
		return [
			['https://example.com/callback'],
			['http://localhost/callback'],
			['http://127.0.0.1:8765/callback'],
			['https://localhost/callback'],
		];
	}

	#[DataProvider('safeRedirectUriProvider')]
	public function testRegisterClientAcceptsSafeRedirectUri(string $redirectUri): void
	{
		$client = $this->clientService()->registerClient('Legit Client', [$redirectUri]);

		self::assertTrue($this->clientService()->validateRedirectUri($client->clientId, $redirectUri));
	}
}
