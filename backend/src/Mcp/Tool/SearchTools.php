<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use RuntimeException;
use Ukolio\Mcp\Dto\McpSearchHitDto;
use Ukolio\Mcp\Dto\McpSearchResultDto;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Search\Dto\SearchFiltersDto;
use Ukolio\Service\Search\Dto\SearchHitDto;
use Ukolio\Service\Search\MeiliClient;

final readonly class SearchTools
{
	private const int MaxLimit = 50;

	public function __construct(
		private McpUserContextInterface $userContext,
		private WorkspaceProviderInterface $workspaceProvider,
		private MeiliClient $meiliClient,
	) {
	}

	/**
	 * Full-text search across task names, descriptions, comments, text/textarea custom field values,
	 * and tag names in the current workspace. Typo-tolerant. Returns up to `limit` matching tasks,
	 * each with a highlighted snippet showing where the match occurred.
	 *
	 * @param string $query Free-text query. Empty string returns the most recently updated tasks.
	 * @param int|null $projectId Optional: restrict matches to a single project.
	 * @param bool $onlyActive When true, hides tasks whose status type is Finish.
	 * @param int $limit Maximum number of hits to return (1-50, default 20).
	 */
	#[McpTool(
		name: 'search_tasks',
		description: 'Typo-tolerant full-text search over tasks (name, description, comments, custom-field text values, tag names) in the current workspace.',
	)]
	public function searchTasks(string $query, ?int $projectId = null, bool $onlyActive = false, int $limit = 20,): McpSearchResultDto
	{
		$workspace = $this->requireWorkspace();

		$boundedLimit = max(1, min(self::MaxLimit, $limit));

		$filters = new SearchFiltersDto(projectId: $projectId, onlyActive: $onlyActive);

		$result = $this->meiliClient->search($workspace->id, $query, $filters, $boundedLimit, 0);

		return new McpSearchResultDto(
			hits: array_map(static fn (SearchHitDto $h): McpSearchHitDto => McpSearchHitDto::fromDto($h), $result->hits),
			totalHits: $result->estimatedTotalHits,
		);
	}

	private function requireWorkspace(): Workspace
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->userContext->getUser());
		if ($workspace === null) {
			throw new RuntimeException('No active workspace.');
		}
		return $workspace;
	}
}
