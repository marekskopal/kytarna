<?php

declare(strict_types=1);

namespace Ukolio\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteGet;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ukolio\Response\ErrorResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Request\RequestServiceInterface;
use Ukolio\Service\Search\Dto\SearchFiltersDto;
use Ukolio\Service\Search\MeiliClient;

final readonly class SearchController
{
	private const int DefaultLimit = 20;
	private const int MaxLimit = 50;

	public function __construct(
		private MeiliClient $meiliClient,
		private WorkspaceProviderInterface $workspaceProvider,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::Search->value)]
	public function actionSearch(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);
		if ($workspace === null) {
			return new ErrorResponse('No active workspace.', 422);
		}

		$query = $request->getQueryParams();

		$q = $this->stringParam($query, 'q') ?? '';
		$limit = $this->intParam($query, 'limit', self::DefaultLimit, 1, self::MaxLimit);
		$offset = $this->intParam($query, 'offset', 0, 0, 1000);
		$projectId = $this->intOrNullParam($query, 'projectId');
		$statusIds = $this->idsParam($query, 'statusIds');
		$onlyActive = $this->boolParam($query, 'onlyActive');

		$filters = new SearchFiltersDto(projectId: $projectId, statusIds: $statusIds, onlyActive: $onlyActive);

		$result = $this->meiliClient->search($workspace->id, $q, $filters, $limit, $offset);

		return new JsonResponse($result);
	}

	/** @param array<array-key, mixed> $query */
	private function stringParam(array $query, string $key): ?string
	{
		if (!isset($query[$key]) || !is_string($query[$key]) || $query[$key] === '') {
			return null;
		}
		return $query[$key];
	}

	/** @param array<array-key, mixed> $query */
	private function intParam(array $query, string $key, int $default, int $min, int $max): int
	{
		if (!isset($query[$key]) || !is_string($query[$key])) {
			return $default;
		}
		return max($min, min($max, (int) $query[$key]));
	}

	/** @param array<array-key, mixed> $query */
	private function intOrNullParam(array $query, string $key): ?int
	{
		if (!isset($query[$key]) || !is_string($query[$key]) || $query[$key] === '') {
			return null;
		}
		$value = (int) $query[$key];
		return $value > 0 ? $value : null;
	}

	/** @param array<array-key, mixed> $query */
	private function boolParam(array $query, string $key): bool
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
	private function idsParam(array $query, string $key): ?array
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
