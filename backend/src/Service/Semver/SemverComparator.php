<?php

declare(strict_types=1);

namespace Ukolio\Service\Semver;

final class SemverComparator
{
	private const REGEX = '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/';

	public static function isValid(string $value): bool
	{
		return (bool) preg_match(self::REGEX, $value);
	}

	private static function compare(string $a, string $b): int
	{
		[$mainA, $preA] = self::splitMainAndPrerelease($a);
		[$mainB, $preB] = self::splitMainAndPrerelease($b);

		$mainCmp = self::compareMain($mainA, $mainB);
		if ($mainCmp !== 0) {
			return $mainCmp;
		}

		if ($preA === null && $preB === null) {
			return 0;
		}
		if ($preA === null) {
			return 1;
		}
		if ($preB === null) {
			return -1;
		}

		return self::comparePrerelease($preA, $preB);
	}

	/**
	 * @param list<string> $versions
	 * @return list<string>
	 */
	public static function sortDescending(array $versions): array
	{
		usort($versions, static fn (string $a, string $b): int => self::compare($b, $a));
		return $versions;
	}

	/** @return array{string, ?string} */
	private static function splitMainAndPrerelease(string $value): array
	{
		$buildPos = strpos($value, '+');
		if ($buildPos !== false) {
			$value = substr($value, 0, $buildPos);
		}

		$prePos = strpos($value, '-');
		if ($prePos === false) {
			return [$value, null];
		}

		return [substr($value, 0, $prePos), substr($value, $prePos + 1)];
	}

	private static function compareMain(string $a, string $b): int
	{
		$partsA = explode('.', $a);
		$partsB = explode('.', $b);

		for ($i = 0; $i < 3; $i++) {
			$cmp = ((int) $partsA[$i]) <=> ((int) $partsB[$i]);
			if ($cmp !== 0) {
				return $cmp;
			}
		}

		return 0;
	}

	private static function comparePrerelease(string $a, string $b): int
	{
		$partsA = explode('.', $a);
		$partsB = explode('.', $b);
		$len = max(count($partsA), count($partsB));

		for ($i = 0; $i < $len; $i++) {
			if (!isset($partsA[$i])) {
				return -1;
			}
			if (!isset($partsB[$i])) {
				return 1;
			}

			$cmp = self::comparePrereleasePart($partsA[$i], $partsB[$i]);
			if ($cmp !== 0) {
				return $cmp;
			}
		}

		return 0;
	}

	private static function comparePrereleasePart(string $a, string $b): int
	{
		$aIsNum = ctype_digit($a);
		$bIsNum = ctype_digit($b);

		if ($aIsNum && $bIsNum) {
			return ((int) $a) <=> ((int) $b);
		}
		if ($aIsNum) {
			return -1;
		}
		if ($bIsNum) {
			return 1;
		}

		return strcmp($a, $b);
	}
}
