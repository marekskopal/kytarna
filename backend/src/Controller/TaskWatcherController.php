<?php

declare(strict_types=1);

namespace Kytario\Controller;

use Kytario\Dto\TaskWatcherDto;
use Kytario\Dto\TaskWatchersDto;
use Kytario\Model\Entity\Task;
use Kytario\Model\Entity\TaskWatcher;
use Kytario\Model\Entity\User;
use Kytario\Response\NotFoundResponse;
use Kytario\Route\Routes;
use Kytario\Service\Provider\TaskCodeResolverInterface;
use Kytario\Service\Provider\TaskWatcherProviderInterface;
use Kytario\Service\Request\RequestServiceInterface;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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
