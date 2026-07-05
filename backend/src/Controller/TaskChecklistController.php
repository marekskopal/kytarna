<?php

declare(strict_types=1);

namespace Ukolio\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Ukolio\Dto\TaskChecklistItemCreateDto;
use Ukolio\Dto\TaskChecklistItemDto;
use Ukolio\Dto\TaskChecklistItemMoveDto;
use Ukolio\Dto\TaskChecklistItemUpdateDto;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskChecklistItem;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\UserRepository;
use Ukolio\Response\ErrorResponse;
use Ukolio\Response\NotAuthorizedResponse;
use Ukolio\Response\NotFoundResponse;
use Ukolio\Response\OkResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Auth\PermissionCheckerInterface;
use Ukolio\Service\Provider\TaskChecklistProviderInterface;
use Ukolio\Service\Provider\TaskCodeResolverInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Request\RequestServiceInterface;

final readonly class TaskChecklistController
{
	public function __construct(
		private TaskCodeResolverInterface $taskCodeResolver,
		private TaskChecklistProviderInterface $checklistProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
		private RequestServiceInterface $requestService,
		private UserRepository $userRepository,
	) {
	}

	#[RouteGet(Routes::TaskChecklist->value)]
	public function actionGetChecklist(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->taskCodeResolver->resolveForUser($user, (string) $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}

		$items = array_map(
			static fn (TaskChecklistItem $item): TaskChecklistItemDto => TaskChecklistItemDto::fromEntity($item),
			$this->checklistProvider->findByTask($task),
		);

		return new JsonResponse($items);
	}

	#[RoutePost(Routes::TaskChecklist->value)]
	public function actionPostChecklistItem(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->taskCodeResolver->resolveForUser($user, (string) $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}
		if (!$this->permissionChecker->canManageTasks($user, $task->project->workspace)) {
			return new NotAuthorizedResponse('You do not have permission to manage this task.');
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, TaskChecklistItemCreateDto::class);
			$assignee = $this->resolveAssignee($task, $dto->assigneeId);
			$item = $this->checklistProvider->createItem($task, $dto->text, $dto->dueDate, $assignee);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(TaskChecklistItemDto::fromEntity($item), 201);
	}

	#[RoutePut(Routes::TaskChecklistItem->value)]
	public function actionPutChecklistItem(ServerRequestInterface $request, int $itemId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$item = $this->loadItemForManage($user, $itemId);
		if (!$item instanceof TaskChecklistItem) {
			return $item;
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, TaskChecklistItemUpdateDto::class);
			$assignee = $dto->assigneeProvided ? $this->resolveAssignee($item->task, $dto->assigneeId) : null;
			$updated = $this->checklistProvider->updateItem(
				item: $item,
				actor: $user,
				text: $dto->text,
				dueDateProvided: $dto->dueDateProvided,
				dueDate: $dto->dueDate,
				assigneeProvided: $dto->assigneeProvided,
				assignee: $assignee,
				checkedProvided: $dto->checkedProvided,
				checked: $dto->checked,
			);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(TaskChecklistItemDto::fromEntity($updated));
	}

	#[RoutePut(Routes::TaskChecklistItemMove->value)]
	public function actionMoveChecklistItem(ServerRequestInterface $request, int $itemId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$item = $this->loadItemForManage($user, $itemId);
		if (!$item instanceof TaskChecklistItem) {
			return $item;
		}

		$dto = $this->requestService->getRequestBodyDto($request, TaskChecklistItemMoveDto::class);
		$moved = $this->checklistProvider->moveItem($item, $dto->position);

		return new JsonResponse(TaskChecklistItemDto::fromEntity($moved));
	}

	#[RouteDelete(Routes::TaskChecklistItem->value)]
	public function actionDeleteChecklistItem(ServerRequestInterface $request, int $itemId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$item = $this->loadItemForManage($user, $itemId);
		if (!$item instanceof TaskChecklistItem) {
			return $item;
		}

		$this->checklistProvider->deleteItem($item);

		return new OkResponse();
	}

	private function loadItemForManage(User $user, int $itemId): TaskChecklistItem|ResponseInterface
	{
		$item = $this->checklistProvider->getItem($itemId);
		if ($item === null || !$this->workspaceProvider->isMember($user, $item->task->project->workspace)) {
			return new NotFoundResponse('Checklist item not found.');
		}
		if (!$this->permissionChecker->canManageTasks($user, $item->task->project->workspace)) {
			return new NotAuthorizedResponse('You do not have permission to manage this task.');
		}

		return $item;
	}

	private function resolveAssignee(Task $task, ?int $assigneeId): ?User
	{
		if ($assigneeId === null) {
			return null;
		}

		$assignee = $this->userRepository->findUserById($assigneeId);
		if ($assignee === null || !$this->workspaceProvider->isMember($assignee, $task->project->workspace)) {
			throw new RuntimeException('Assignee must be a member of the task workspace.');
		}

		return $assignee;
	}
}
