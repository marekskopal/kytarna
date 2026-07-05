<?php

declare(strict_types=1);

namespace Kytario\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Kytario\Dto\TaskCreateDto;
use Kytario\Dto\TaskDto;
use Kytario\Dto\TaskListDto;
use Kytario\Dto\TaskListItemDto;
use Kytario\Dto\TaskListQueryDto;
use Kytario\Dto\TaskMoveDto;
use Kytario\Dto\TaskUpdateDto;
use Kytario\Model\Entity\Priority;
use Kytario\Model\Entity\Project;
use Kytario\Model\Entity\Task;
use Kytario\Model\Entity\User;
use Kytario\Model\Repository\UserRepository;
use Kytario\Response\ErrorResponse;
use Kytario\Response\NotFoundResponse;
use Kytario\Response\OkResponse;
use Kytario\Route\Routes;
use Kytario\Service\Provider\PriorityProviderInterface;
use Kytario\Service\Provider\ProjectProviderInterface;
use Kytario\Service\Provider\StatusProviderInterface;
use Kytario\Service\Provider\SubtaskProviderInterface;
use Kytario\Service\Provider\TaskChecklistProviderInterface;
use Kytario\Service\Provider\TaskCodeResolverInterface;
use Kytario\Service\Provider\TaskFieldValueProviderInterface;
use Kytario\Service\Provider\TaskProviderInterface;
use Kytario\Service\Provider\TaskTagProviderInterface;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use Kytario\Service\Request\RequestServiceInterface;

final readonly class TaskController
{
	public function __construct(
		private ProjectProviderInterface $projectProvider,
		private TaskProviderInterface $taskProvider,
		private TaskCodeResolverInterface $taskCodeResolver,
		private StatusProviderInterface $statusProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private TaskFieldValueProviderInterface $taskFieldValueProvider,
		private TaskTagProviderInterface $taskTagProvider,
		private SubtaskProviderInterface $subtaskProvider,
		private TaskChecklistProviderInterface $checklistProvider,
		private PriorityProviderInterface $priorityProvider,
		private RequestServiceInterface $requestService,
		private UserRepository $userRepository,
	) {
	}

	#[RouteGet(Routes::Tasks->value)]
	public function actionGetWorkspaceTasks(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);
		if ($workspace === null) {
			return new ErrorResponse('No active workspace.', 422);
		}

		try {
			$listQuery = TaskListQueryDto::fromQueryParams($request->getQueryParams());
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 400);
		}

		$tasks = iterator_to_array(
			$this->taskProvider->getTasksInWorkspace(
				$workspace,
				$listQuery->limit,
				$listQuery->offset,
				$listQuery->orderBy,
				$listQuery->direction,
				$listQuery->search,
				$listQuery->statusIds,
				$listQuery->onlyActive,
				$listQuery->tagIds,
				$listQuery->assigneeIds,
				$listQuery->subtaskFilter,
				$listQuery->archived,
				$listQuery->dueFrom,
				$listQuery->dueTo,
			),
			false,
		);

		$count = $this->taskProvider->countTasksInWorkspace(
			$workspace,
			$listQuery->search,
			$listQuery->statusIds,
			$listQuery->onlyActive,
			$listQuery->tagIds,
			$listQuery->assigneeIds,
			$listQuery->subtaskFilter,
			$listQuery->archived,
			$listQuery->dueFrom,
			$listQuery->dueTo,
		);

		$taskIds = array_map(static fn (Task $t): int => $t->id, $tasks);
		$tagsByTaskId = $this->taskTagProvider->getTagIdsByTaskIds($taskIds);
		$subtaskCounts = $this->subtaskProvider->getSubtaskCounts($taskIds);
		$checklistCounts = $this->checklistProvider->getCounts($taskIds);

		return new JsonResponse(new TaskListDto(
			tasks: array_map(
				static fn (Task $t): TaskListItemDto => TaskListItemDto::fromEntity(
					$t,
					$tagsByTaskId[$t->id] ?? [],
					$subtaskCounts[$t->id]['total'] ?? 0,
					$subtaskCounts[$t->id]['done'] ?? 0,
					$checklistCounts[$t->id]['total'] ?? 0,
					$checklistCounts[$t->id]['done'] ?? 0,
				),
				$tasks,
			),
			count: $count,
		));
	}

	#[RouteGet(Routes::ProjectTasks->value)]
	public function actionGetTasks(ServerRequestInterface $request, int $projectId): ResponseInterface
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

		$projectTasks = iterator_to_array($this->taskProvider->getTasksByProject($project, includeArchived: false), false);
		$tagsByTaskId = $this->taskTagProvider->getTagIdsByTaskIds(array_map(static fn (Task $t): int => $t->id, $projectTasks));

		$tasks = array_map(
			fn (Task $t): TaskDto => TaskDto::fromEntity(
				$t,
				$this->taskFieldValueProvider->findByTask($t),
				$tagsByTaskId[$t->id] ?? [],
			),
			$projectTasks,
		);

		return new JsonResponse($tasks);
	}

	#[RoutePost(Routes::ProjectTasks->value)]
	public function actionPostTask(ServerRequestInterface $request, int $projectId): ResponseInterface
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

		try {
			$dto = $this->requestService->getRequestBodyDto($request, TaskCreateDto::class);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		$status = $this->statusProvider->getStatus($dto->statusId);
		if ($status === null || $status->workflow->project->id !== $project->id) {
			return new NotFoundResponse('Status not found in this project.');
		}

		try {
			$assignee = $dto->assigneeIdProvided
				? $this->resolveAssignee($project, $dto->assigneeId)
				: $user;
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		try {
			$priority = $this->resolvePriority($project, $dto->priorityId, $dto->priorityName);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		if ($priority === null) {
			return new ErrorResponse('Workspace has no priorities configured.', 422);
		}

		try {
			$task = $this->taskProvider->createTask(
				author: $user,
				project: $project,
				status: $status,
				name: $dto->name,
				description: $dto->description,
				priority: $priority,
				dueDate: $dto->dueDate,
				assignee: $assignee,
				fieldValues: $dto->fieldValues,
				tagIds: $dto->tagIds,
				startDate: $dto->startDate,
			);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(
			TaskDto::fromEntity($task, $this->taskFieldValueProvider->findByTask($task), $this->taskTagProvider->getTagIdsForTask($task)),
		);
	}

	#[RouteGet(Routes::Task->value)]
	public function actionGetTask(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->loadTaskInScope($user, $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}

		return new JsonResponse(
			TaskDto::fromEntity($task, $this->taskFieldValueProvider->findByTask($task), $this->taskTagProvider->getTagIdsForTask($task)),
		);
	}

	#[RoutePut(Routes::Task->value)]
	public function actionPutTask(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->loadTaskInScope($user, $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, TaskUpdateDto::class);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		$status = $this->statusProvider->getStatus($dto->statusId);
		if ($status === null || $status->workflow->project->id !== $task->project->id) {
			return new NotFoundResponse('Status not found in this project.');
		}

		try {
			$assignee = $dto->assigneeIdProvided
				? $this->resolveAssignee($task->project, $dto->assigneeId)
				: $task->assignee;
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		try {
			$priority = $this->resolvePriority($task->project, $dto->priorityId, $dto->priorityName) ?? $task->priority;
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		try {
			$task = $this->taskProvider->updateTask(
				author: $user,
				task: $task,
				name: $dto->name,
				description: $dto->description,
				priority: $priority,
				dueDate: $dto->dueDate,
				status: $status,
				assignee: $assignee,
				fieldValues: $dto->fieldValues,
				tagIds: $dto->tagIds,
				startDate: $dto->startDate,
			);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(
			TaskDto::fromEntity($task, $this->taskFieldValueProvider->findByTask($task), $this->taskTagProvider->getTagIdsForTask($task)),
		);
	}

	#[RoutePut(Routes::TaskMove->value)]
	public function actionPutTaskMove(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->loadTaskInScope($user, $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, TaskMoveDto::class);

		$newStatus = $this->statusProvider->getStatus($dto->statusId);
		if ($newStatus === null || $newStatus->workflow->project->id !== $task->project->id) {
			return new NotFoundResponse('Status not found in this project.');
		}

		$task = $this->taskProvider->moveTask($user, $task, $newStatus, $dto->position);

		return new JsonResponse(
			TaskDto::fromEntity($task, $this->taskFieldValueProvider->findByTask($task), $this->taskTagProvider->getTagIdsForTask($task)),
		);
	}

	#[RoutePost(Routes::TaskArchive->value)]
	public function actionPostTaskArchive(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->loadTaskInScope($user, $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}

		$task = $this->taskProvider->archiveTask($user, $task);

		return new JsonResponse(
			TaskDto::fromEntity($task, $this->taskFieldValueProvider->findByTask($task), $this->taskTagProvider->getTagIdsForTask($task)),
		);
	}

	#[RoutePost(Routes::TaskUnarchive->value)]
	public function actionPostTaskUnarchive(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->loadTaskInScope($user, $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}

		$task = $this->taskProvider->unarchiveTask($user, $task);

		return new JsonResponse(
			TaskDto::fromEntity($task, $this->taskFieldValueProvider->findByTask($task), $this->taskTagProvider->getTagIdsForTask($task)),
		);
	}

	#[RoutePost(Routes::TaskDuplicate->value)]
	public function actionPostTaskDuplicate(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->loadTaskInScope($user, $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}

		try {
			$duplicate = $this->taskProvider->duplicateTask($user, $task);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(
			TaskDto::fromEntity(
				$duplicate,
				$this->taskFieldValueProvider->findByTask($duplicate),
				$this->taskTagProvider->getTagIdsForTask($duplicate),
			),
		);
	}

	#[RouteDelete(Routes::Task->value)]
	public function actionDeleteTask(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->loadTaskInScope($user, $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}

		$this->taskProvider->deleteTask($user, $task);

		return new OkResponse();
	}

	private function loadTaskInScope(User $user, int|string $taskId): ?Task
	{
		return $this->taskCodeResolver->resolveForUser($user, (string) $taskId);
	}

	private function resolveAssignee(Project $project, ?int $assigneeId): ?User
	{
		if ($assigneeId === null) {
			return null;
		}

		$assignee = $this->userRepository->findUserById($assigneeId);
		if ($assignee === null || !$this->workspaceProvider->isMember($assignee, $project->workspace)) {
			throw new RuntimeException('Assignee must be a member of the project workspace.');
		}

		return $assignee;
	}

	private function resolvePriority(Project $project, ?int $priorityId, ?string $priorityName): ?Priority
	{
		if ($priorityId !== null) {
			$priority = $this->priorityProvider->getPriority($project->workspace, $priorityId);
			if ($priority === null) {
				throw new RuntimeException('Priority not found in this workspace.');
			}
			return $priority;
		}

		if ($priorityName !== null) {
			$priority = $this->priorityProvider->findPriorityByName($project->workspace, $priorityName);
			if ($priority === null) {
				throw new RuntimeException('Priority "' . $priorityName . '" not found in this workspace.');
			}
			return $priority;
		}

		return $this->priorityProvider->getDefaultForWorkspace($project->workspace);
	}
}
