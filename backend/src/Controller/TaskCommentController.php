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
use Ukolio\Dto\TaskCommentCreateDto;
use Ukolio\Dto\TaskCommentDto;
use Ukolio\Dto\TaskCommentUpdateDto;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskComment;
use Ukolio\Model\Entity\User;
use Ukolio\Response\ErrorResponse;
use Ukolio\Response\NotAuthorizedResponse;
use Ukolio\Response\NotFoundResponse;
use Ukolio\Response\OkResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Auth\PermissionCheckerInterface;
use Ukolio\Service\Provider\TaskCodeResolverInterface;
use Ukolio\Service\Provider\TaskCommentProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Request\RequestServiceInterface;

final readonly class TaskCommentController
{
	public function __construct(
		private TaskCodeResolverInterface $taskCodeResolver,
		private TaskCommentProviderInterface $taskCommentProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::TaskComments->value)]
	public function actionGetComments(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->loadTaskInScope($user, $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}

		$comments = array_map(
			static fn (TaskComment $c): TaskCommentDto => TaskCommentDto::fromEntity($c),
			$this->taskCommentProvider->findByTask($task),
		);

		return new JsonResponse($comments);
	}

	#[RoutePost(Routes::TaskComments->value)]
	public function actionPostComment(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->loadTaskInScope($user, $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, TaskCommentCreateDto::class);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		$parent = null;
		if ($dto->parentCommentId !== null) {
			$parent = $this->taskCommentProvider->getComment($dto->parentCommentId);
			if ($parent === null || $parent->task->id !== $task->id) {
				return new NotFoundResponse('Parent comment not found.');
			}
		}

		try {
			$comment = $this->taskCommentProvider->createComment($user, $task, $dto->body, $parent);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(TaskCommentDto::fromEntity($comment), 201);
	}

	#[RoutePut(Routes::TaskComment->value)]
	public function actionPutComment(ServerRequestInterface $request, int $commentId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$comment = $this->taskCommentProvider->getComment($commentId);
		if ($comment === null) {
			return new NotFoundResponse('Comment not found.');
		}

		$workspace = $comment->task->project->workspace;
		if (!$this->workspaceProvider->isMember($user, $workspace)) {
			return new NotFoundResponse('Comment not found.');
		}

		if (!$this->permissionChecker->canEditTaskComment($user, $workspace, $comment)) {
			return new NotAuthorizedResponse('You do not have permission to edit this comment.');
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, TaskCommentUpdateDto::class);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		try {
			$updated = $this->taskCommentProvider->updateComment($user, $comment, $dto->body);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(TaskCommentDto::fromEntity($updated));
	}

	#[RouteDelete(Routes::TaskComment->value)]
	public function actionDeleteComment(ServerRequestInterface $request, int $commentId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$comment = $this->taskCommentProvider->getComment($commentId);
		if ($comment === null) {
			return new NotFoundResponse('Comment not found.');
		}

		$workspace = $comment->task->project->workspace;
		if (!$this->workspaceProvider->isMember($user, $workspace)) {
			return new NotFoundResponse('Comment not found.');
		}

		if (!$this->permissionChecker->canDeleteTaskComment($user, $workspace, $comment)) {
			return new NotAuthorizedResponse('You do not have permission to delete this comment.');
		}

		$this->taskCommentProvider->deleteComment($user, $comment);

		return new OkResponse();
	}

	private function loadTaskInScope(User $user, int|string $taskId): ?Task
	{
		return $this->taskCodeResolver->resolveForUser($user, (string) $taskId);
	}
}
