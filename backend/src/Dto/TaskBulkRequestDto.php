<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use RuntimeException;
use Ukolio\Service\Provider\Enum\BulkOpEnum;

/** @implements ArrayFactoryInterface<array<string, mixed>> */
final readonly class TaskBulkRequestDto implements ArrayFactoryInterface
{
	public const int MAX_IDS = 200;

	/**
	 * @param list<int> $ids
	 * @param array<string, mixed> $payload
	 */
	public function __construct(public array $ids, public BulkOpEnum $op, public array $payload,)
	{
	}

	public static function fromArray(array $data): static
	{
		$ids = self::parseIds($data['ids'] ?? null);

		$opValue = $data['op'] ?? null;
		if (!is_string($opValue)) {
			throw new RuntimeException('Field "op" is required.');
		}
		$op = BulkOpEnum::tryFrom($opValue);
		if ($op === null) {
			throw new RuntimeException(sprintf('Field "op" is invalid: "%s".', $opValue));
		}

		$rawPayload = $data['payload'] ?? [];
		if (!is_array($rawPayload)) {
			throw new RuntimeException('Field "payload" must be an object.');
		}
		$payload = [];
		foreach ($rawPayload as $key => $value) {
			$payload[(string) $key] = $value;
		}

		return new self(ids: $ids, op: $op, payload: $payload);
	}

	/**
	 * @param mixed $raw
	 * @return list<int>
	 */
	private static function parseIds(mixed $raw): array
	{
		if (!is_array($raw)) {
			throw new RuntimeException('Field "ids" must be a list of integers.');
		}
		$ids = [];
		foreach ($raw as $entry) {
			if (is_int($entry) && $entry > 0) {
				$ids[] = $entry;
				continue;
			}
			if (is_string($entry) && ctype_digit($entry) && (int) $entry > 0) {
				$ids[] = (int) $entry;
				continue;
			}

			throw new RuntimeException('Field "ids" must contain positive integers.');
		}
		$ids = array_values(array_unique($ids));
		if ($ids === []) {
			throw new RuntimeException('Field "ids" must not be empty.');
		}
		if (count($ids) > self::MAX_IDS) {
			throw new RuntimeException(sprintf('Field "ids" must not exceed %d entries.', self::MAX_IDS));
		}
		return $ids;
	}
}
