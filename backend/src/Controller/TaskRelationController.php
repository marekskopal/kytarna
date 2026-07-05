<?php

declare(strict_types=1);

namespace Ukolio\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Ukolio\Dto\TaskRelationCreateDto;
use Ukolio\Dto\TaskRelationDto;
use Ukolio\Dto\TaskRelationListDto;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskRelation;
use Ukolio\Model\Entity\User;
use Ukolio\Response\ErrorResponse;
use Ukolio\Response\NotFoundResponse;
use Ukolio\Response\OkResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Provider\TaskCodeResolverInterface;
use Ukolio\Service\Provider\TaskRelationProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Request\RequestServiceInterface;

final readonly class TaskRelationController
{
	public function __construct(
		private TaskCodeResolverInterface $taskCodeResolver,
		private TaskRelationProviderInterface $taskRelationProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::TaskRelations->value)]
	public function actionGetRelations(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->loadTaskInScope($user, $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}

		$outgoing = array_map(
			static fn (TaskRelation $rel): TaskRelationDto => TaskRelationDto::fromEntity($rel, forSourceSide: true),
			$this->taskRelationProvider->findOutgoing($task),
		);
		$incoming = array_map(
			static fn (TaskRelation $rel): TaskRelationDto => TaskRelationDto::fromEntity($rel, forSourceSide: false),
			$this->taskRelationProvider->findIncoming($task),
		);

		return new JsonResponse(new TaskRelationListDto(outgoing: $outgoing, incoming: $incoming));
	}

	#[RoutePost(Routes::TaskRelations->value)]
	public function actionPostRelation(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$source = $this->loadTaskInScope($user, $taskId);
		if ($source === null) {
			return new NotFoundResponse('Task not found.');
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, TaskRelationCreateDto::class);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		$target = $this->loadTaskInScope($user, (string) $dto->targetTaskId);
		if ($target === null) {
			return new NotFoundResponse('Target task not found.');
		}

		try {
			$relation = $this->taskRelationProvider->createRelation($user, $source, $target, $dto->type);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(TaskRelationDto::fromEntity($relation, forSourceSide: true), 201);
	}

	#[RouteDelete(Routes::TaskRelation->value)]
	public function actionDeleteRelation(ServerRequestInterface $request, int $relationId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$relation = $this->taskRelationProvider->getRelation($relationId);
		if ($relation === null) {
			return new NotFoundResponse('Relation not found.');
		}

		if (!$this->workspaceProvider->isMember($user, $relation->sourceTask->project->workspace)) {
			return new NotFoundResponse('Relation not found.');
		}

		$this->taskRelationProvider->deleteRelation($user, $relation);

		return new OkResponse();
	}

	private function loadTaskInScope(User $user, int|string $taskId): ?Task
	{
		return $this->taskCodeResolver->resolveForUser($user, (string) $taskId);
	}
}
