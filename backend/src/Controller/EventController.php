<?php

declare(strict_types=1);

namespace Kytarna\Controller;

use Kytarna\Dto\EventDto;
use Kytarna\Dto\WorkspaceAgentStatsDto;
use Kytarna\Model\Entity\Enum\ActorTypeEnum;
use Kytarna\Model\Entity\Enum\EventTypeEnum;
use Kytarna\Model\Entity\Event;
use Kytarna\Model\Repository\LectureRepository;
use Kytarna\Response\NotAuthorizedResponse;
use Kytarna\Response\NotFoundResponse;
use Kytarna\Route\Routes;
use Kytarna\Service\Auth\PermissionCheckerInterface;
use Kytarna\Service\Provider\CourseProviderInterface;
use Kytarna\Service\Provider\EventProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Kytarna\Service\Request\RequestServiceInterface;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteGet;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class EventController
{
	public function __construct(
		private CourseProviderInterface $courseProvider,
		private EventProviderInterface $eventProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
		private RequestServiceInterface $requestService,
		private LectureRepository $lectureRepository,
	) {
	}

	#[RouteGet(Routes::CourseEvents->value)]
	public function actionGetEvents(ServerRequestInterface $request, int $courseId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);
		if ($workspace === null) {
			return new NotFoundResponse('Course with id "' . $courseId . '" was not found.');
		}

		$course = $this->courseProvider->getCourse($workspace, $courseId);
		if ($course === null) {
			return new NotFoundResponse('Course with id "' . $courseId . '" was not found.');
		}

		$query = $request->getQueryParams();
		$limit = is_numeric($query['limit'] ?? null) ? (int) $query['limit'] : 100;
		$offset = is_numeric($query['offset'] ?? null) ? (int) $query['offset'] : 0;

		$eventEntities = iterator_to_array($this->eventProvider->getEvents($course, $limit, $offset), false);
		$codeByLectureId = $this->buildLectureCodeMap($eventEntities);

		$events = array_map(
			static fn (Event $e): EventDto => EventDto::fromEntity(
				$e,
				$e->lectureId !== null ? ($codeByLectureId[$e->lectureId] ?? null) : null,
			),
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
		$codeByLectureId = $this->buildLectureCodeMap($eventEntities);

		$events = array_map(
			static fn (Event $e): EventDto => EventDto::fromEntity(
				$e,
				$e->lectureId !== null ? ($codeByLectureId[$e->lectureId] ?? null) : null,
			),
			$eventEntities,
		);

		return new JsonResponse($events);
	}

	/**
	 * @param list<Event> $events
	 * @return array<int, string>
	 */
	private function buildLectureCodeMap(array $events): array
	{
		$lectureIds = [];
		foreach ($events as $event) {
			if ($event->lectureId !== null) {
				$lectureIds[$event->lectureId] = true;
			}
		}
		if ($lectureIds === []) {
			return [];
		}

		$map = [];
		foreach ($this->lectureRepository->findByIds(array_keys($lectureIds)) as $lecture) {
			$map[$lecture->id] = $lecture->course->prefix . '-' . $lecture->sequenceNumber;
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
		$lecturesCreatedLast24h = $this->eventProvider->countWorkspaceEventsOfTypeSince($workspace, EventTypeEnum::LectureCreated, $since);
		$lecturesClosedLast24h = $this->eventProvider->countWorkspaceEventsOfTypeSince($workspace, EventTypeEnum::LectureMoved, $since);

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
			lecturesCreatedLast24h: $lecturesCreatedLast24h,
			lecturesClosedLast24h: $lecturesClosedLast24h,
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
