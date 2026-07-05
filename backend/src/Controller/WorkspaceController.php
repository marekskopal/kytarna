<?php

declare(strict_types=1);

namespace Kytario\Controller;

use Kytario\Dto\WorkspaceCreateDto;
use Kytario\Dto\WorkspaceDto;
use Kytario\Dto\WorkspaceMemberDto;
use Kytario\Dto\WorkspaceMemberRoleUpdateDto;
use Kytario\Dto\WorkspaceTransferOwnershipDto;
use Kytario\Dto\WorkspaceUpdateDto;
use Kytario\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytario\Model\Entity\Workspace;
use Kytario\Model\Entity\WorkspaceUser;
use Kytario\Response\ErrorResponse;
use Kytario\Response\NotAuthorizedResponse;
use Kytario\Response\NotFoundResponse;
use Kytario\Response\OkResponse;
use Kytario\Route\Routes;
use Kytario\Service\Auth\PermissionCheckerInterface;
use Kytario\Service\Provider\WorkspaceMcpClientProviderInterface;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use Kytario\Service\Request\RequestServiceInterface;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePatch;
use MarekSkopal\Router\Attribute\RoutePost;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final readonly class WorkspaceController
{
	public function __construct(
		private WorkspaceProviderInterface $workspaceProvider,
		private WorkspaceMcpClientProviderInterface $mcpClientProvider,
		private PermissionCheckerInterface $permissionChecker,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::Workspaces->value)]
	public function actionGetWorkspaces(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);

		$workspaces = [];
		foreach ($this->workspaceProvider->getMemberships($user) as $membership) {
			$workspaces[] = WorkspaceDto::fromEntity($membership->workspace);
		}

		return new JsonResponse($workspaces);
	}

	#[RoutePost(Routes::Workspaces->value)]
	public function actionPostWorkspace(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$dto = $this->requestService->getRequestBodyDto($request, WorkspaceCreateDto::class);

		try {
			$workspace = $this->workspaceProvider->createWorkspace($user, $dto->name);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(WorkspaceDto::fromEntity($workspace));
	}

	#[RoutePut(Routes::Workspace->value)]
	public function actionPutWorkspace(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		if (!$this->permissionChecker->canManageWorkspace($user, $workspace)) {
			return new NotAuthorizedResponse('Only the owner can update the workspace.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, WorkspaceUpdateDto::class);

		try {
			$updated = $this->workspaceProvider->updateWorkspace($workspace, $dto->name ?? $workspace->name);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(WorkspaceDto::fromEntity($updated));
	}

	#[RouteDelete(Routes::Workspace->value)]
	public function actionDeleteWorkspace(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		if (!$this->permissionChecker->canManageWorkspace($user, $workspace)) {
			return new NotAuthorizedResponse('Only the owner can delete the workspace.');
		}

		$this->workspaceProvider->deleteWorkspace($workspace);

		return new OkResponse();
	}

	#[RoutePost(Routes::WorkspaceSwitch->value)]
	public function actionPostSwitch(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		if (!$this->workspaceProvider->isMember($user, $workspace)) {
			return new NotAuthorizedResponse('You are not a member of this workspace.');
		}

		$this->workspaceProvider->switchCurrentWorkspace($user, $workspace);

		return new JsonResponse(WorkspaceDto::fromEntity($workspace));
	}

	#[RouteGet(Routes::WorkspaceMembers->value)]
	public function actionGetMembers(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		if (!$this->permissionChecker->canViewWorkspace($user, $workspace)) {
			return new NotAuthorizedResponse('You are not a member of this workspace.');
		}

		$members = [];
		foreach ($this->workspaceProvider->getMembers($workspace) as $membership) {
			$members[] = WorkspaceMemberDto::fromEntity($membership);
		}

		return new JsonResponse($members);
	}

	#[RoutePatch(Routes::WorkspaceMember->value)]
	public function actionPatchMember(ServerRequestInterface $request, int $workspaceId, int $userId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		$target = $this->findMembershipByUserId($workspace, $userId);
		if ($target === null) {
			return new NotFoundResponse('Member not found.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, WorkspaceMemberRoleUpdateDto::class);
		$newRole = WorkspaceRoleEnum::tryFrom($dto->role);
		if ($newRole === null) {
			return new ErrorResponse('Invalid role.', 422);
		}

		if (!$this->permissionChecker->canChangeRole($user, $workspace, $target, $newRole)) {
			return new NotAuthorizedResponse('You cannot change this member\'s role.');
		}

		$this->workspaceProvider->changeMemberRole($user, $target, $newRole);

		return new JsonResponse(WorkspaceMemberDto::fromEntity($target));
	}

	#[RoutePost(Routes::WorkspaceTransferOwnership->value)]
	public function actionPostTransferOwnership(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		if (!$this->permissionChecker->canManageWorkspace($user, $workspace)) {
			return new NotAuthorizedResponse('Only the current owner can transfer ownership.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, WorkspaceTransferOwnershipDto::class);
		$target = $this->findMembershipByUserId($workspace, $dto->userId);
		if ($target === null) {
			return new ErrorResponse('Target user is not a member of this workspace.', 422);
		}

		if ($target->user->id === $workspace->owner->id) {
			return new ErrorResponse('Target user is already the owner.', 422);
		}

		$this->workspaceProvider->transferOwnership($user, $workspace, $target);

		return new JsonResponse(WorkspaceDto::fromEntity($workspace));
	}

	#[RouteDelete(Routes::WorkspaceMember->value)]
	public function actionDeleteMember(ServerRequestInterface $request, int $workspaceId, int $userId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		$target = $this->findMembershipByUserId($workspace, $userId);
		if ($target === null) {
			return new NotFoundResponse('Member not found.');
		}

		if ($target->role === WorkspaceRoleEnum::Owner) {
			return new ErrorResponse('The owner cannot be removed. Transfer ownership first.', 422);
		}

		if (!$this->permissionChecker->canRemoveMember($user, $workspace, $target)) {
			return new NotAuthorizedResponse('You cannot remove this member.');
		}

		$this->workspaceProvider->removeMember($target);

		return new OkResponse();
	}

	#[RouteGet(Routes::WorkspaceMcpClients->value)]
	public function actionGetMcpClients(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		if (!$this->permissionChecker->canViewWorkspace($user, $workspace)) {
			return new NotAuthorizedResponse('You are not a member of this workspace.');
		}

		return new JsonResponse($this->mcpClientProvider->getClientsForWorkspace($workspace));
	}

	#[RoutePost(Routes::WorkspaceMcpClientRevoke->value)]
	public function actionPostRevokeMcpClient(ServerRequestInterface $request, int $workspaceId, string $clientId,): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		if (!$this->permissionChecker->canManageMembers($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have permission to revoke MCP client access.');
		}

		return new JsonResponse(['revokedTokens' => $this->mcpClientProvider->revokeClient($workspace, $clientId)]);
	}

	private function findMembershipByUserId(Workspace $workspace, int $userId): ?WorkspaceUser
	{
		foreach ($this->workspaceProvider->getMembers($workspace) as $membership) {
			if ($membership->user->id === $userId) {
				return $membership;
			}
		}

		return null;
	}
}
