<?php

declare(strict_types=1);

namespace Ukolio\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RoutePost;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ukolio\Dto\StatusCreateDto;
use Ukolio\Dto\StatusDto;
use Ukolio\Dto\StatusMoveDto;
use Ukolio\Dto\StatusUpdateDto;
use Ukolio\Model\Entity\Status;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workflow;
use Ukolio\Response\ErrorResponse;
use Ukolio\Response\NotAuthorizedResponse;
use Ukolio\Response\NotFoundResponse;
use Ukolio\Response\OkResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Auth\PermissionCheckerInterface;
use Ukolio\Service\Provider\StatusProviderInterface;
use Ukolio\Service\Provider\WorkflowProviderInterface;
use Ukolio\Service\Request\RequestServiceInterface;

final readonly class StatusController
{
	public function __construct(
		private WorkflowProviderInterface $workflowProvider,
		private StatusProviderInterface $statusProvider,
		private PermissionCheckerInterface $permissionChecker,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RoutePost(Routes::WorkflowStatuses->value)]
	public function actionPostStatus(ServerRequestInterface $request, int $workflowId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workflow = $this->workflowProvider->getWorkflow($workflowId);
		if (!$this->canAccessWorkflow($user, $workflow)) {
			return new NotFoundResponse('Workflow not found.');
		}
		assert($workflow !== null);

		if (!$this->permissionChecker->canManageProjects($user, $workflow->project->workspace)) {
			return new NotAuthorizedResponse('You do not have permission to manage workflow statuses.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, StatusCreateDto::class);

		$status = $this->statusProvider->createStatus(
			workflow: $workflow,
			name: $dto->name,
			color: $dto->color,
			type: $dto->type,
			position: $dto->position,
		);

		return new JsonResponse(StatusDto::fromEntity($status));
	}

	#[RoutePut(Routes::Status->value)]
	public function actionPutStatus(ServerRequestInterface $request, int $statusId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$status = $this->statusProvider->getStatus($statusId);
		if (!$this->canAccessStatus($user, $status)) {
			return new NotFoundResponse('Status not found.');
		}
		assert($status !== null);

		if (!$this->permissionChecker->canManageProjects($user, $status->workflow->project->workspace)) {
			return new NotAuthorizedResponse('You do not have permission to manage workflow statuses.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, StatusUpdateDto::class);
		$status = $this->statusProvider->updateStatus($status, $dto->name, $dto->color, $dto->type);

		return new JsonResponse(StatusDto::fromEntity($status));
	}

	#[RoutePut(Routes::StatusMove->value)]
	public function actionPutStatusMove(ServerRequestInterface $request, int $statusId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$status = $this->statusProvider->getStatus($statusId);
		if (!$this->canAccessStatus($user, $status)) {
			return new NotFoundResponse('Status not found.');
		}
		assert($status !== null);

		if (!$this->permissionChecker->canManageProjects($user, $status->workflow->project->workspace)) {
			return new NotAuthorizedResponse('You do not have permission to manage workflow statuses.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, StatusMoveDto::class);
		$status = $this->statusProvider->moveStatus($status, $dto->position);

		return new JsonResponse(StatusDto::fromEntity($status));
	}

	#[RouteDelete(Routes::Status->value)]
	public function actionDeleteStatus(ServerRequestInterface $request, int $statusId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$status = $this->statusProvider->getStatus($statusId);
		if (!$this->canAccessStatus($user, $status)) {
			return new NotFoundResponse('Status not found.');
		}
		assert($status !== null);

		if (!$this->permissionChecker->canManageProjects($user, $status->workflow->project->workspace)) {
			return new NotAuthorizedResponse('You do not have permission to manage workflow statuses.');
		}

		$siblings = iterator_to_array($this->statusProvider->getStatuses($status->workflow), false);
		if (count($siblings) <= 1) {
			return new ErrorResponse('Cannot delete the last status of a workflow.', 422);
		}

		$this->statusProvider->deleteStatus($status);

		return new OkResponse();
	}

	private function canAccessWorkflow(User $user, ?Workflow $workflow): bool
	{
		if ($workflow === null) {
			return false;
		}
		return $this->permissionChecker->canViewWorkspace($user, $workflow->project->workspace);
	}

	private function canAccessStatus(User $user, ?Status $status): bool
	{
		if ($status === null) {
			return false;
		}
		return $this->permissionChecker->canViewWorkspace($user, $status->workflow->project->workspace);
	}
}
