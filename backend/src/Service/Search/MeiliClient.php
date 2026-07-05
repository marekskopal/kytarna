<?php

declare(strict_types=1);

namespace Ukolio\Service\Search;

use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use Psr\Log\LoggerInterface;
use Throwable;
use Ukolio\Model\Repository\TaskRepository;
use Ukolio\Service\Search\Dto\SearchFiltersDto;
use Ukolio\Service\Search\Dto\SearchHitDto;
use Ukolio\Service\Search\Dto\SearchResultDto;

final class MeiliClient
{
	private const string IndexSuffix = '_tasks';

	private const array IndexSettings = [
		'searchableAttributes' => ['name', 'code', 'description', 'comments', 'fieldValues', 'tags'],
		'filterableAttributes' => ['workspaceId', 'projectId', 'statusId', 'statusType', 'assigneeId', 'priorityId', 'tags'],
		'sortableAttributes' => ['createdAt', 'updatedAt'],
		'rankingRules' => ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness', 'updatedAt:desc'],
		'displayedAttributes' => ['id', 'code', 'workspaceId', 'projectId', 'statusId', 'name'],
		'typoTolerance' => [
			'enabled' => true,
			'minWordSizeForTypos' => ['oneTypo' => 4, 'twoTypos' => 8],
		],
	];

	private ?Client $client = null;

	public function __construct(
		private readonly TaskDocumentBuilder $documentBuilder,
		private readonly TaskRepository $taskRepository,
		private readonly LoggerInterface $logger,
	) {
	}

	public function indexName(): string
	{
		$prefix = (string) getenv('MEILI_INDEX_PREFIX');
		if ($prefix === '') {
			$prefix = 'ukolio';
		}
		return $prefix . self::IndexSuffix;
	}

	public function ensureIndex(): void
	{
		$client = $this->client();
		$indexName = $this->indexName();

		try {
			$client->getIndex($indexName);
		} catch (Throwable) {
			$client->createIndex($indexName, ['primaryKey' => 'id']);
		}

		$client->index($indexName)->updateSettings(self::IndexSettings);
	}

	public function indexTask(int $taskId): void
	{
		$task = $this->taskRepository->findById($taskId);
		if ($task === null) {
			$this->deleteTask($taskId);
			return;
		}

		$document = $this->documentBuilder->build($task);
		$this->index()->addDocuments([$document], 'id');
	}

	public function deleteTask(int $taskId): void
	{
		try {
			$this->index()->deleteDocument($taskId);
		} catch (Throwable $e) {
			$this->logger->warning('Meilisearch delete failed for task ' . $taskId . ': ' . $e->getMessage());
		}
	}

	public function deleteAllDocuments(): void
	{
		try {
			$this->index()->deleteAllDocuments();
		} catch (Throwable $e) {
			$this->logger->warning('Meilisearch deleteAllDocuments failed: ' . $e->getMessage());
		}
	}

	public function search(int $workspaceId, string $query, ?SearchFiltersDto $filters, int $limit, int $offset): SearchResultDto
	{
		// Filter values are concatenated into the Meili expression, so every value is
		// int-cast here — never rely on upstream typing alone (a raw string value
		// could break out of the expression and cross workspace boundaries).
		$filterExpressions = ['workspaceId = ' . $workspaceId];
		if ($filters !== null) {
			if ($filters->projectId !== null) {
				$filterExpressions[] = 'projectId = ' . $filters->projectId;
			}
			if ($filters->statusIds !== null && $filters->statusIds !== []) {
				$filterExpressions[] = 'statusId IN [' . implode(', ', array_map(intval(...), $filters->statusIds)) . ']';
			}
			if ($filters->onlyActive) {
				$filterExpressions[] = 'statusType != "Finish"';
			}
		}

		$result = $this->index()->search($query, [
			'limit' => $limit,
			'offset' => $offset,
			'filter' => $filterExpressions,
			'attributesToHighlight' => ['name', 'description', 'comments', 'fieldValues', 'tags'],
			'attributesToCrop' => ['description', 'comments', 'fieldValues'],
			'cropLength' => 30,
			'highlightPreTag' => '<mark>',
			'highlightPostTag' => '</mark>',
		]);

		$hits = [];
		foreach ($result->getHits() as $hit) {
			$hits[] = $this->buildHit($hit);
		}

		return new SearchResultDto(
			hits: $hits,
			estimatedTotalHits: $result->getEstimatedTotalHits() ?? count($hits),
			processingTimeMs: $result->getProcessingTimeMs(),
		);
	}

	/** @param array<array-key, mixed> $hit */
	private function buildHit(array $hit): SearchHitDto
	{
		$formattedRaw = $hit['_formatted'] ?? null;
		$formatted = is_array($formattedRaw) ? $formattedRaw : [];
		[$snippet, $matchedIn] = $this->pickSnippet($formatted, $hit);

		return new SearchHitDto(
			id: $this->intField($hit, 'id'),
			code: $this->stringField($hit, 'code'),
			projectId: $this->intField($hit, 'projectId'),
			statusId: $this->intField($hit, 'statusId'),
			name: $this->stringField($hit, 'name'),
			snippet: $snippet,
			matchedIn: $matchedIn,
		);
	}

	/**
	 * @param array<array-key, mixed> $formatted
	 * @param array<array-key, mixed> $hit
	 * @return array{0: ?string, 1: string}
	 */
	private function pickSnippet(array $formatted, array $hit): array
	{
		$name = $this->stringField($hit, 'name');
		$formattedName = $this->stringField($formatted, 'name');

		if ($formattedName !== '' && $formattedName !== $name) {
			return [$formattedName, 'name'];
		}

		foreach (['description', 'comments', 'fieldValues', 'tags'] as $field) {
			$text = $this->normalizeFormatted($formatted[$field] ?? null);
			if ($text !== null && str_contains($text, '<mark>')) {
				return [$text, $field];
			}
		}

		return [null, 'name'];
	}

	private function normalizeFormatted(mixed $value): ?string
	{
		if (is_string($value)) {
			return $value;
		}
		if (is_array($value)) {
			$parts = [];
			foreach ($value as $item) {
				if (is_string($item) && str_contains($item, '<mark>')) {
					$parts[] = $item;
				}
			}
			return $parts === [] ? null : implode(' … ', $parts);
		}
		return null;
	}

	/** @param array<array-key, mixed> $hit */
	private function intField(array $hit, string $key): int
	{
		$value = $hit[$key] ?? null;
		if (is_int($value)) {
			return $value;
		}
		if (is_string($value) && is_numeric($value)) {
			return (int) $value;
		}
		return 0;
	}

	/** @param array<array-key, mixed> $hit */
	private function stringField(array $hit, string $key): string
	{
		$value = $hit[$key] ?? null;
		return is_string($value) ? $value : '';
	}

	private function index(): Indexes
	{
		return $this->client()->index($this->indexName());
	}

	private function client(): Client
	{
		if ($this->client === null) {
			$host = (string) getenv('MEILI_HOST');
			$port = (string) getenv('MEILI_PORT');
			$key = (string) getenv('MEILI_MASTER_KEY');
			$url = 'http://' . $host . ':' . $port;
			$this->client = new Client($url, $key === '' ? null : $key);
		}
		return $this->client;
	}
}
