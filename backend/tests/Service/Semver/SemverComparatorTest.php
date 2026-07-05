<?php

declare(strict_types=1);

namespace Ukolio\Tests\Service\Semver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ukolio\Service\Semver\SemverComparator;

#[CoversClass(SemverComparator::class)]
final class SemverComparatorTest extends TestCase
{
	/** @return iterable<array{string, bool}> */
	public static function validityCases(): iterable
	{
		yield 'plain release' => ['1.0.0', true];
		yield 'multi-digit' => ['12.34.56', true];
		yield 'prerelease' => ['1.0.0-alpha', true];
		yield 'prerelease numeric' => ['1.0.0-alpha.1', true];
		yield 'build metadata' => ['1.0.0+build.7', true];
		yield 'prerelease + build' => ['1.0.0-rc.1+build.7', true];
		yield 'missing patch' => ['1.0', false];
		yield 'leading v' => ['v1.0.0', false];
		yield 'empty' => ['', false];
		yield 'extra junk' => ['1.0.0 abc', false];
	}

	#[DataProvider('validityCases')]
	public function testIsValid(string $value, bool $expected): void
	{
		self::assertSame($expected, SemverComparator::isValid($value));
	}

	public function testSortDescendingMain(): void
	{
		$sorted = SemverComparator::sortDescending(['1.0.0', '2.0.0', '1.1.0']);
		self::assertSame(['2.0.0', '1.1.0', '1.0.0'], $sorted);
	}

	public function testSortDescendingPlacesPrereleaseBeforeRelease(): void
	{
		// Per semver 2.0 §11: 1.0.0-alpha < 1.0.0, so desc sort puts 1.0.0 first.
		$sorted = SemverComparator::sortDescending(['1.0.0-alpha', '1.0.0', '1.0.0-beta']);
		self::assertSame(['1.0.0', '1.0.0-beta', '1.0.0-alpha'], $sorted);
	}

	public function testSortDescendingNumericPrereleaseOrdering(): void
	{
		$sorted = SemverComparator::sortDescending(['1.0.0-alpha.10', '1.0.0-alpha.2', '1.0.0-alpha.1']);
		self::assertSame(['1.0.0-alpha.10', '1.0.0-alpha.2', '1.0.0-alpha.1'], $sorted);
	}
}
