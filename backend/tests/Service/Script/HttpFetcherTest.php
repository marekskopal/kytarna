<?php

declare(strict_types=1);

namespace Ukolio\Tests\Service\Script;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Ukolio\Service\Script\Host\HttpFetcher;

#[CoversClass(HttpFetcher::class)]
final class HttpFetcherTest extends TestCase
{
	/** @param list<string> $allowed */
	private function assertHostAllowed(string $url, array $allowed): void
	{
		$method = new ReflectionMethod(HttpFetcher::class, 'assertHostAllowed');
		$method->invoke(new HttpFetcher(), $url, $allowed);
	}

	public function testEmptyAllowlistAllowsAnyHost(): void
	{
		$this->expectNotToPerformAssertions();
		$this->assertHostAllowed('https://anything.example.com/x', []);
	}

	public function testExactHostMatchIsAllowed(): void
	{
		$this->expectNotToPerformAssertions();
		$this->assertHostAllowed('https://hooks.slack.com/services/x', ['hooks.slack.com']);
	}

	public function testSubdomainOfAllowedHostIsAllowed(): void
	{
		$this->expectNotToPerformAssertions();
		$this->assertHostAllowed('https://api.github.com/repos', ['github.com']);
	}

	public function testHostNotInAllowlistIsRejected(): void
	{
		$this->expectException(RuntimeException::class);
		$this->assertHostAllowed('https://evil.example.com', ['github.com']);
	}

	public function testSuffixLookalikeIsNotTreatedAsSubdomain(): void
	{
		// "notgithub.com" must NOT match the "github.com" pattern.
		$this->expectException(RuntimeException::class);
		$this->assertHostAllowed('https://notgithub.com', ['github.com']);
	}

	/** @return array<array-key, mixed> */
	private function resolveSafeTarget(string $url): array
	{
		$method = new ReflectionMethod(HttpFetcher::class, 'resolveSafeTarget');
		$result = $method->invoke(new HttpFetcher(), $url);
		self::assertIsArray($result);
		return $result;
	}

	/** @return list<array{string}> 169.254.169.254 is the cloud-metadata endpoint. */
	public static function internalTargetProvider(): array
	{
		return [
			['http://127.0.0.1/'],
			['http://169.254.169.254/latest/meta-data/'],
			['http://10.0.0.5/'],
			['http://192.168.1.1/'],
			['http://172.16.0.1/'],
			['http://[::1]/'],
			['http://0.0.0.0/'],
		];
	}

	#[DataProvider('internalTargetProvider')]
	public function testInternalAddressesAreRejected(string $url): void
	{
		$this->expectException(RuntimeException::class);
		$this->resolveSafeTarget($url);
	}

	public function testPublicLiteralIpIsAllowedAndPinned(): void
	{
		$target = $this->resolveSafeTarget('https://8.8.8.8/resolve');
		self::assertSame('8.8.8.8', $target['host']);
		self::assertSame(443, $target['port']);
		self::assertSame(['8.8.8.8'], $target['ips']);
	}

	public function testInternalAddressIsRejectedEvenWhenAllowlisted(): void
	{
		// The allowlist is an *additional* restriction; it can never re-enable an internal target.
		$this->expectException(RuntimeException::class);
		$fetcher = new HttpFetcher();
		$fetcher->fetch('http://169.254.169.254/', [], ['169.254.169.254']);
	}
}
