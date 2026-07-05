<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use RuntimeException;

/**
 * Type-coerces untyped payload values from JSON / JSON-RPC input for BulkTaskProvider.
 * Lives outside BulkTaskProvider purely to keep that class's cognitive complexity under threshold.
 */
final readonly class BulkPayloadParser
{
	/** @param array<string, mixed> $payload */
	public function intOrNull(array $payload, string $key): ?int
	{
		if (!array_key_exists($key, $payload)) {
			return null;
		}
		return $this->coerceInt($payload[$key], $key);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return list<int>
	 */
	public function intList(array $payload, string $key): array
	{
		if (!array_key_exists($key, $payload)) {
			return [];
		}
		$value = $payload[$key];
		if (!is_array($value)) {
			throw new RuntimeException(sprintf('Payload "%s" must be a list of integers.', $key));
		}
		$ids = [];
		foreach ($value as $entry) {
			$ids[] = $this->coercePositiveInt($entry, $key);
		}
		return array_values(array_unique($ids));
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	public function sanitise(array $payload): array
	{
		$out = [];
		foreach ($payload as $key => $value) {
			$out[$key] = $this->sanitiseValue($value);
		}
		return $out;
	}

	private function coerceInt(mixed $value, string $key): ?int
	{
		if ($value === null) {
			return null;
		}
		if (is_int($value)) {
			return $value;
		}
		if (is_string($value) && ctype_digit($value)) {
			return (int) $value;
		}

		throw new RuntimeException(sprintf('Payload "%s" must be an integer.', $key));
	}

	private function coercePositiveInt(mixed $entry, string $key): int
	{
		if (is_int($entry) && $entry > 0) {
			return $entry;
		}
		if (is_string($entry) && ctype_digit($entry) && (int) $entry > 0) {
			return (int) $entry;
		}

		throw new RuntimeException(sprintf('Payload "%s" must contain positive integers.', $key));
	}

	private function sanitiseValue(mixed $value): mixed
	{
		if (is_scalar($value) || $value === null) {
			return $value;
		}
		if (!is_array($value)) {
			return null;
		}
		$flat = [];
		foreach ($value as $entry) {
			if (is_scalar($entry) || $entry === null) {
				$flat[] = $entry;
			}
		}
		return $flat;
	}
}
