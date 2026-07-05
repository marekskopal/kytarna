<?php

declare(strict_types=1);

namespace Ukolio\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Ukolio\Dto\TaskRecurrenceDto;
use Ukolio\Dto\TaskRecurrenceWriteDto;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\User;
use Ukolio\Response\ErrorResponse;
use Ukolio\Response\NotAuthorizedResponse;
use Ukolio\Response\NotFoundResponse;
use Ukolio\Response\OkResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Auth\PermissionCheckerInterface;
use Ukolio\Service\Provider\TaskCodeResolverInterface;
use Ukolio\Service\Provider\TaskRecurrenceProviderInterface;
use Ukolio\Service\Request\RequestServiceInterface;

final readonly class TaskRecurrenceController
{
	public function __construct(
		private TaskCodeResolverInterface $taskCodeResolver,
		private TaskRecurrenceProviderInterface $recurrenceProvider,
		private PermissionCheckerInterface $permissionChecker,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::TaskRecurrence->value)]
	public function actionGet(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->taskCodeResolver->resolveForUser($user, (string) $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}

		$recurrence = $this->recurrenceProvider->findByTask($task);

		return new JsonResponse($recurrence === null ? null : TaskRecurrenceDto::fromEntity($recurrence));
	}

	#[RoutePut(Routes::TaskRecurrence->value)]
	public function actionPut(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->loadForManage($user, $taskId);
		if (!$task instanceof Task) {
			return $task;
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, TaskRecurrenceWriteDto::class);
			$recurrence = $this->recurrenceProvider->set($user, $task, $dto->toConfig());
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(TaskRecurrenceDto::fromEntity($recurrence));
	}

	#[RouteDelete(Routes::TaskRecurrence->value)]
	public function actionDelete(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->loadForManage($user, $taskId);
		if (!$task instanceof Task) {
			return $task;
		}

		$this->recurrenceProvider->clear($user, $task);

		return new OkResponse();
	}

	private function loadForManage(User $user, int|string $taskId): Task|ResponseInterface
	{
		$task = $this->taskCodeResolver->resolveForUser($user, (string) $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}
		if (!$this->permissionChecker->canManageTasks($user, $task->project->workspace)) {
			return new NotAuthorizedResponse('You do not have permission to manage this task.');
		}

		return $task;
	}
}
