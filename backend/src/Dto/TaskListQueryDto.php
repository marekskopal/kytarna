<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use DateTimeImmutable;
use RuntimeException;
use Ukolio\Model\Repository\Enum\ArchivedFilterEnum;
use Ukolio\Model\Repository\Enum\OrderDirectionEnum;
use Ukolio\Model\Repository\Enum\SubtaskFilterEnum;
use Ukolio\Model\Repository\Enum\TaskOrderByEnum;
use const PHP_INT_MAX;

/** Parsed and validated query parameters of the workspace-wide task list (GET /api/tasks). */
final readonly class TaskListQueryDto
{
	/**
	 * @param list<int>|null $statusIds
	 * @param list<int>|null $tagIds
	 * @param list<int>|null $assigneeIds
	 */
	public function __construct(
		public TaskOrderByEnum $orderBy,
		public OrderDirectionEnum $direction,
		public SubtaskFilterEnum $subtaskFilter,
		public ArchivedFilterEnum $archived,
		public int $limit,
		public int $offset,
		public ?string $search,
		public ?array $statusIds,
		public ?array $tagIds,
		public ?array $assigneeIds,
		public bool $onlyActive,
		public ?DateTimeImmutable $dueFrom,
		public ?DateTimeImmutable $dueTo,
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
			subtaskFilter: self::parseSubtaskFilter($query),
			archived: self::parseArchivedFilter($query),
			limit: self::intParam($query, 'limit', 50, 1, 200),
			offset: self::intParam($query, 'offset', 0, 0, PHP_INT_MAX),
			search: self::stringParam($query, 'search'),
			statusIds: self::idsParam($query, 'statusIds'),
			tagIds: self::idsParam($query, 'tagIds'),
			assigneeIds: self::idsParam($query, 'assigneeIds'),
			onlyActive: self::boolParam($query, 'onlyActive'),
			dueFrom: self::dateParam($query, 'dueFrom'),
			dueTo: self::dateParam($query, 'dueTo'),
		);
	}

	/**
	 * Parses a strict YYYY-MM-DD date param (used for the due-date range filter). Returns null when
	 * absent/empty; throws RuntimeException on a malformed value (the message is safe for a 400).
	 *
	 * @param array<array-key, mixed> $query
	 */
	private static function dateParam(array $query, string $key): ?DateTimeImmutable
	{
		if (!isset($query[$key]) || !is_string($query[$key]) || $query[$key] === '') {
			return null;
		}
		$date = DateTimeImmutable::createFromFormat('!Y-m-d', $query[$key]);
		$errors = DateTimeImmutable::getLastErrors();
		if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
			throw new RuntimeException(sprintf('Invalid %s value; expected YYYY-MM-DD.', $key));
		}
		return $date;
	}

	/** @param array<array-key, mixed> $query */
	private static function parseOrderBy(array $query): TaskOrderByEnum
	{
		if (!isset($query['orderBy']) || !is_string($query['orderBy'])) {
			return TaskOrderByEnum::CreatedAt;
		}
		return TaskOrderByEnum::tryFrom($query['orderBy']) ?? throw new RuntimeException('Invalid orderBy value.');
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
	private static function parseSubtaskFilter(array $query): SubtaskFilterEnum
	{
		if (!isset($query['subtaskFilter']) || !is_string($query['subtaskFilter'])) {
			return SubtaskFilterEnum::All;
		}
		return SubtaskFilterEnum::tryFrom($query['subtaskFilter']) ?? throw new RuntimeException('Invalid subtaskFilter value.');
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
}
