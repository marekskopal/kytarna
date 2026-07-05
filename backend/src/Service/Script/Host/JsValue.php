<?php

declare(strict_types=1);

namespace Kytario\Service\Script\Host;

/**
 * Normalises values that cross the JS→PHP boundary. Depending on the ext-v8js build, V8Js hands
 * plain JS objects to PHP as `stdClass` or as a `V8Object` instance; both expose their members via
 * `get_object_vars()`, so we accept any object here. JS arrays arrive as PHP arrays. Host methods
 * accept `mixed` and funnel through here.
 */
final class JsValue
{
	/** @return array<string, mixed> */
	public static function toAssoc(mixed $value): array
	{
		$source = match (true) {
			is_object($value) => get_object_vars($value),
			is_array($value) => $value,
			default => [],
		};

		$out = [];
		foreach ($source as $key => $item) {
			$out[(string) $key] = $item;
		}

		return $out;
	}

	public static function string(mixed $value, ?string $default = null): ?string
	{
		if (is_string($value)) {
			return $value;
		}

		if (is_int($value) || is_float($value)) {
			return (string) $value;
		}

		return $default;
	}

	public static function int(mixed $value): ?int
	{
		if (is_int($value)) {
			return $value;
		}

		if (is_string($value) && $value !== '' && ctype_digit(ltrim($value, '-'))) {
			return (int) $value;
		}

		if (is_float($value)) {
			return (int) $value;
		}

		return null;
	}

	/** @return list<int> */
	public static function intList(mixed $value): array
	{
		$source = is_object($value) ? get_object_vars($value) : $value;
		if (!is_array($source)) {
			return [];
		}

		$ids = [];
		foreach ($source as $item) {
			$id = self::int($item);
			if ($id !== null) {
				$ids[] = $id;
			}
		}

		return $ids;
	}
}
