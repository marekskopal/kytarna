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
use Ukolio\Dto\PriorityCreateDto;
use Ukolio\Dto\PriorityDto;
use Ukolio\Dto\PriorityMoveDto;
use Ukolio\Dto\PriorityUpdateDto;
use Ukolio\Model\Entity\Priority;
use Ukolio\Response\ErrorResponse;
use Ukolio\Response\NotAuthorizedResponse;
use Ukolio\Response\NotFoundResponse;
use Ukolio\Response\OkResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Auth\PermissionCheckerInterface;
use Ukolio\Service\Provider\PriorityInUseException;
use Ukolio\Service\Provider\PriorityProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Request\RequestServiceInterface;

final readonly class PriorityController
{
	public function __construct(
		private PriorityProviderInterface $priorityProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::WorkspacePriorities->value)]
	public function actionGetPriorities(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}
		if (!$this->permissionChecker->canViewWorkspace($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have access to this workspace.');
		}

		$priorities = array_map(
			fn (Priority $p): PriorityDto => PriorityDto::fromEntity($p),
			iterator_to_array($this->priorityProvider->getPriorities($workspace), false),
		);

		return new JsonResponse($priorities);
	}

	#[RoutePost(Routes::WorkspacePriorities->value)]
	public function actionPostPriority(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}
		if (!$this->permissionChecker->canManagePriorities($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have permission to manage priorities.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, PriorityCreateDto::class);

		try {
			$priority = $this->priorityProvider->createPriority($workspace, $dto->name, $dto->color, $dto->isDefault);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(PriorityDto::fromEntity($priority));
	}

	#[RoutePut(Routes::WorkspacePriority->value)]
	public function actionPutPriority(ServerRequestInterface $request, int $workspaceId, int $priorityId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}
		if (!$this->permissionChecker->canManagePriorities($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have permission to manage priorities.');
		}

		$priority = $this->priorityProvider->getPriority($workspace, $priorityId);
		if ($priority === null) {
			return new NotFoundResponse('Priority not found.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, PriorityUpdateDto::class);

		try {
			$priority = $this->priorityProvider->updatePriority($priority, $dto->name, $dto->color, $dto->isDefault);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(PriorityDto::fromEntity($priority));
	}

	#[RouteDelete(Routes::WorkspacePriority->value)]
	public function actionDeletePriority(ServerRequestInterface $request, int $workspaceId, int $priorityId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}
		if (!$this->permissionChecker->canManagePriorities($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have permission to manage priorities.');
		}

		$priority = $this->priorityProvider->getPriority($workspace, $priorityId);
		if ($priority === null) {
			return new NotFoundResponse('Priority not found.');
		}

		try {
			$this->priorityProvider->deletePriority($priority);
		} catch (PriorityInUseException $e) {
			return new JsonResponse(
				['code' => 409, 'message' => $e->getMessage(), 'dependentTaskCount' => $e->dependentTaskCount],
				409,
			);
		}

		return new OkResponse();
	}

	#[RoutePut(Routes::PriorityMove->value)]
	public function actionMovePriority(ServerRequestInterface $request, int $workspaceId, int $priorityId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}
		if (!$this->permissionChecker->canManagePriorities($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have permission to manage priorities.');
		}

		$priority = $this->priorityProvider->getPriority($workspace, $priorityId);
		if ($priority === null) {
			return new NotFoundResponse('Priority not found.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, PriorityMoveDto::class);
		$priority = $this->priorityProvider->movePriority($priority, $dto->position);

		return new JsonResponse(PriorityDto::fromEntity($priority));
	}
}
