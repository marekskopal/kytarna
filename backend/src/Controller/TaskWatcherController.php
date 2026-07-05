<?php

declare(strict_types=1);

namespace Ukolio\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ukolio\Dto\TaskWatcherDto;
use Ukolio\Dto\TaskWatchersDto;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskWatcher;
use Ukolio\Model\Entity\User;
use Ukolio\Response\NotFoundResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Provider\TaskCodeResolverInterface;
use Ukolio\Service\Provider\TaskWatcherProviderInterface;
use Ukolio\Service\Request\RequestServiceInterface;

final readonly class TaskWatcherController
{
	public function __construct(
		private TaskCodeResolverInterface $taskCodeResolver,
		private TaskWatcherProviderInterface $taskWatcherProvider,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::TaskWatchers->value)]
	public function actionList(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->loadTaskInScope($user, $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}

		return new JsonResponse($this->watchersDto($task, $user));
	}

	#[RoutePost(Routes::TaskWatch->value)]
	public function actionWatch(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->loadTaskInScope($user, $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}

		$this->taskWatcherProvider->watch($task, $user);

		return new JsonResponse($this->watchersDto($task, $user));
	}

	#[RouteDelete(Routes::TaskWatch->value)]
	public function actionUnwatch(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->loadTaskInScope($user, $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}

		$this->taskWatcherProvider->unwatch($task, $user);

		return new JsonResponse($this->watchersDto($task, $user));
	}

	private function watchersDto(Task $task, User $user): TaskWatchersDto
	{
		$watchers = array_map(
			static fn (TaskWatcher $w): TaskWatcherDto => TaskWatcherDto::fromEntity($w),
			$this->taskWatcherProvider->listWatchers($task),
		);

		return new TaskWatchersDto($watchers, $this->taskWatcherProvider->isWatching($task, $user));
	}

	private function loadTaskInScope(User $user, int|string $taskId): ?Task
	{
		return $this->taskCodeResolver->resolveForUser($user, (string) $taskId);
	}
}
