<?php

declare(strict_types=1);

namespace Ukolio\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteGet;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ukolio\Dto\EventDto;
use Ukolio\Dto\WorkspaceAgentStatsDto;
use Ukolio\Model\Entity\Enum\ActorTypeEnum;
use Ukolio\Model\Entity\Enum\EventTypeEnum;
use Ukolio\Model\Entity\Event;
use Ukolio\Model\Repository\TaskRepository;
use Ukolio\Response\NotAuthorizedResponse;
use Ukolio\Response\NotFoundResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Auth\PermissionCheckerInterface;
use Ukolio\Service\Provider\EventProviderInterface;
use Ukolio\Service\Provider\ProjectProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Request\RequestServiceInterface;

final readonly class EventController
{
	public function __construct(
		private ProjectProviderInterface $projectProvider,
		private EventProviderInterface $eventProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
		private RequestServiceInterface $requestService,
		private TaskRepository $taskRepository,
	) {
	}

	#[RouteGet(Routes::ProjectEvents->value)]
	public function actionGetEvents(ServerRequestInterface $request, int $projectId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);
		if ($workspace === null) {
			return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
		}

		$project = $this->projectProvider->getProject($workspace, $projectId);
		if ($project === null) {
			return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
		}

		$query = $request->getQueryParams();
		$limit = is_numeric($query['limit'] ?? null) ? (int) $query['limit'] : 100;
		$offset = is_numeric($query['offset'] ?? null) ? (int) $query['offset'] : 0;

		$eventEntities = iterator_to_array($this->eventProvider->getEvents($project, $limit, $offset), false);
		$codeByTaskId = $this->buildTaskCodeMap($eventEntities);

		$events = array_map(
			static fn (Event $e): EventDto => EventDto::fromEntity($e, $e->taskId !== null ? ($codeByTaskId[$e->taskId] ?? null) : null),
			$eventEntities,
		);

		return new JsonResponse($events);
	}

	#[RouteGet(Routes::WorkspaceEvents->value)]
	public function actionGetWorkspaceEvents(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		if (!$this->permissionChecker->canViewWorkspace($user, $workspace)) {
			return new NotAuthorizedResponse('You are not a member of this workspace.');
		}

		$query = $request->getQueryParams();
		$limit = is_numeric($query['limit'] ?? null) ? min(500, max(1, (int) $query['limit'])) : 100;
		$offset = is_numeric($query['offset'] ?? null) ? max(0, (int) $query['offset']) : 0;
		$actorType = $this->parseActorType(is_string($query['actorType'] ?? null) ? $query['actorType'] : null);

		$eventEntities = iterator_to_array($this->eventProvider->getWorkspaceEvents($workspace, $actorType, $limit, $offset), false);
		$codeByTaskId = $this->buildTaskCodeMap($eventEntities);

		$events = array_map(
			static fn (Event $e): EventDto => EventDto::fromEntity($e, $e->taskId !== null ? ($codeByTaskId[$e->taskId] ?? null) : null),
			$eventEntities,
		);

		return new JsonResponse($events);
	}

	/**
	 * @param list<Event> $events
	 * @return array<int, string>
	 */
	private function buildTaskCodeMap(array $events): array
	{
		$taskIds = [];
		foreach ($events as $event) {
			if ($event->taskId !== null) {
				$taskIds[$event->taskId] = true;
			}
		}
		if ($taskIds === []) {
			return [];
		}

		$map = [];
		foreach ($this->taskRepository->findByIds(array_keys($taskIds)) as $task) {
			$map[$task->id] = $task->project->prefix . '-' . $task->sequenceNumber;
		}
		return $map;
	}

	#[RouteGet(Routes::WorkspaceAgentStats->value)]
	public function actionGetWorkspaceAgentStats(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		if (!$this->permissionChecker->canViewWorkspace($user, $workspace)) {
			return new NotAuthorizedResponse('You are not a member of this workspace.');
		}

		$since = time() - 86400;

		$eventsLast24h = $this->eventProvider->countWorkspaceEventsSince($workspace, $since);
		$tasksCreatedLast24h = $this->eventProvider->countWorkspaceEventsOfTypeSince($workspace, EventTypeEnum::TaskCreated, $since);
		$tasksClosedLast24h = $this->eventProvider->countWorkspaceEventsOfTypeSince($workspace, EventTypeEnum::TaskMoved, $since);

		$activeAgentNames = [];
		foreach ($this->eventProvider->getWorkspaceEvents($workspace, ActorTypeEnum::Agent, 500, 0) as $event) {
			if ($event->createdAt->getTimestamp() < $since) {
				continue;
			}
			$name = $event->mcpClientName ?? $event->mcpClientId;
			if ($name !== null && !in_array($name, $activeAgentNames, true)) {
				$activeAgentNames[] = $name;
			}
		}

		return new JsonResponse(new WorkspaceAgentStatsDto(
			eventsLast24h: $eventsLast24h,
			tasksCreatedLast24h: $tasksCreatedLast24h,
			tasksClosedLast24h: $tasksClosedLast24h,
			activeAgents: count($activeAgentNames),
			activeAgentNames: $activeAgentNames,
		));
	}

	private function parseActorType(?string $value): ?ActorTypeEnum
	{
		if ($value === null || $value === '') {
			return null;
		}

		return ActorTypeEnum::tryFrom($value);
	}
}
