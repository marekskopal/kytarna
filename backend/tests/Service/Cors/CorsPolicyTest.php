<?php

declare(strict_types=1);

namespace Kytario\Tests\Service\Cors;

use Kytario\Service\Cors\CorsPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CorsPolicy::class)]
final class CorsPolicyTest extends TestCase
{
	public function testEmptyValueProducesEmptyPolicy(): void
	{
		$policy = CorsPolicy::fromEnvValue('');

		self::assertSame([], $policy->origins());
		self::assertFalse($policy->allowsAnyOrigin());
		self::assertNull($policy->resolveAllowedOrigin('https://app.example.com'));
		self::assertNull($policy->resolveAllowedOrigin(null));
	}

	public function testWildcardAllowsAnyOrigin(): void
	{
		$policy = CorsPolicy::fromEnvValue('*');

		self::assertTrue($policy->allowsAnyOrigin());
		self::assertSame('*', $policy->resolveAllowedOrigin('https://app.example.com'));
		self::assertSame('*', $policy->resolveAllowedOrigin(null));
	}

	public function testCommaSeparatedListSplits(): void
	{
		$policy = CorsPolicy::fromEnvValue('https://a.example.com, https://b.example.com');

		self::assertSame(['https://a.example.com', 'https://b.example.com'], $policy->origins());
		self::assertFalse($policy->allowsAnyOrigin());
	}

	public function testWhitespaceSeparatedListSplits(): void
	{
		$policy = CorsPolicy::fromEnvValue("https://a.example.com\nhttps://b.example.com");

		self::assertSame(['https://a.example.com', 'https://b.example.com'], $policy->origins());
	}

	public function testDuplicatesAreCollapsed(): void
	{
		$policy = CorsPolicy::fromEnvValue('https://a.example.com,https://a.example.com');

		self::assertSame(['https://a.example.com'], $policy->origins());
	}

	public function testMatchingOriginIsReflected(): void
	{
		$policy = CorsPolicy::fromEnvValue('https://a.example.com,https://b.example.com');

		self::assertSame('https://b.example.com', $policy->resolveAllowedOrigin('https://b.example.com'));
	}

	public function testUnmatchedOriginIsRejected(): void
	{
		$policy = CorsPolicy::fromEnvValue('https://a.example.com');

		self::assertNull($policy->resolveAllowedOrigin('https://evil.example.com'));
		self::assertNull($policy->resolveAllowedOrigin(null));
	}

	public function testWildcardEntryInMixedListAllowsAny(): void
	{
		$policy = CorsPolicy::fromEnvValue('https://a.example.com,*');

		self::assertTrue($policy->allowsAnyOrigin());
		self::assertSame('*', $policy->resolveAllowedOrigin('https://anything.test'));
	}
}
