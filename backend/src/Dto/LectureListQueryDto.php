<?php

declare(strict_types=1);

namespace Kytarna\Dto;

use Kytarna\Model\Entity\Enum\LearningStatusEnum;
use Kytarna\Model\Repository\Enum\ArchivedFilterEnum;
use Kytarna\Model\Repository\Enum\LectureOrderByEnum;
use Kytarna\Model\Repository\Enum\OrderDirectionEnum;
use RuntimeException;
use const PHP_INT_MAX;
use const SORT_REGULAR;

/** Parsed and validated query parameters of the workspace-wide lecture list (GET /api/lectures). */
final readonly class LectureListQueryDto
{
	/**
	 * @param list<LearningStatusEnum>|null $statuses
	 * @param list<int>|null $tagIds
	 */
	public function __construct(
		public LectureOrderByEnum $orderBy,
		public OrderDirectionEnum $direction,
		public ArchivedFilterEnum $archived,
		public int $limit,
		public int $offset,
		public ?string $search,
		public ?array $statuses,
		public ?array $tagIds,
		public bool $onlyActive,
	) {
	}

	/**
	 * Throws RuntimeException on invalid enum-like parameters (the message is safe for a 400 response).
	 *
	 * @param array<array-key, mixed> $query
	 */
	public static function fromQueryParams(array $query): self
	{
		return new self(
			orderBy: self::parseOrderBy($query),
			direction: self::parseDirection($query),
			archived: self::parseArchivedFilter($query),
			limit: self::intParam($query, 'limit', 50, 1, 200),
			offset: self::intParam($query, 'offset', 0, 0, PHP_INT_MAX),
			search: self::stringParam($query, 'search'),
			statuses: self::statusesParam($query, 'statuses'),
			tagIds: self::idsParam($query, 'tagIds'),
			onlyActive: self::boolParam($query, 'onlyActive'),
		);
	}

	/** @param array<array-key, mixed> $query */
	private static function parseOrderBy(array $query): LectureOrderByEnum
	{
		if (!isset($query['orderBy']) || !is_string($query['orderBy'])) {
			return LectureOrderByEnum::CreatedAt;
		}
		return LectureOrderByEnum::tryFrom($query['orderBy']) ?? throw new RuntimeException('Invalid orderBy value.');
	}

	/** @param array<array-key, mixed> $query */
	private static function parseDirection(array $query): OrderDirectionEnum
	{
		if (!isset($query['orderDirection']) || !is_string($query['orderDirection'])) {
			return OrderDirectionEnum::Desc;
		}
		return OrderDirectionEnum::tryFrom(strtoupper($query['orderDirection']))
			?? throw new RuntimeException('Invalid orderDirection value.');
	}

	/** @param array<array-key, mixed> $query */
	private static function parseArchivedFilter(array $query): ArchivedFilterEnum
	{
		if (!isset($query['archived']) || !is_string($query['archived'])) {
			return ArchivedFilterEnum::Active;
		}
		return ArchivedFilterEnum::tryFrom($query['archived']) ?? throw new RuntimeException('Invalid archived value.');
	}

	/** @param array<array-key, mixed> $query */
	private static function intParam(array $query, string $key, int $default, int $min, int $max): int
	{
		if (!isset($query[$key]) || !is_string($query[$key])) {
			return $default;
		}
		return max($min, min($max, (int) $query[$key]));
	}

	/** @param array<array-key, mixed> $query */
	private static function stringParam(array $query, string $key): ?string
	{
		if (!isset($query[$key]) || !is_string($query[$key]) || $query[$key] === '') {
			return null;
		}
		return $query[$key];
	}

	/** @param array<array-key, mixed> $query */
	private static function boolParam(array $query, string $key): bool
	{
		if (!isset($query[$key]) || !is_string($query[$key])) {
			return false;
		}
		return $query[$key] === '1' || $query[$key] === 'true';
	}

	/**
	 * @param array<array-key, mixed> $query
	 * @return list<int>|null
	 */
	private static function idsParam(array $query, string $key): ?array
	{
		if (!isset($query[$key]) || !is_string($query[$key]) || $query[$key] === '') {
			return null;
		}
		$parsed = array_values(array_filter(
			array_map('intval', explode('|', $query[$key])),
			static fn (int $id): bool => $id > 0,
		));
		return $parsed === [] ? null : $parsed;
	}

	/**
	 * @param array<array-key, mixed> $query
	 * @return list<LearningStatusEnum>|null
	 */
	private static function statusesParam(array $query, string $key): ?array
	{
		if (!isset($query[$key]) || !is_string($query[$key]) || $query[$key] === '') {
			return null;
		}
		$parsed = [];
		foreach (explode('|', $query[$key]) as $raw) {
			$status = LearningStatusEnum::fromLoose($raw);
			if ($status !== null) {
				$parsed[] = $status;
			}
		}
		return $parsed === [] ? null : array_values(array_unique($parsed, SORT_REGULAR));
	}
}
