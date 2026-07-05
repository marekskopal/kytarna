<?php

declare(strict_types=1);

namespace Kytario\Controller\Admin;

use Kytario\Dto\AdminAddMemberDto;
use Kytario\Dto\AdminWorkspaceDto;
use Kytario\Dto\WorkspaceDto;
use Kytario\Dto\WorkspaceMemberDto;
use Kytario\Dto\WorkspaceTransferOwnershipDto;
use Kytario\Dto\WorkspaceUpdateDto;
use Kytario\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytario\Model\Entity\Workspace;
use Kytario\Model\Entity\WorkspaceUser;
use Kytario\Response\ConflictResponse;
use Kytario\Response\ErrorResponse;
use Kytario\Response\NotAuthorizedResponse;
use Kytario\Response\NotFoundResponse;
use Kytario\Response\OkResponse;
use Kytario\Route\Routes;
use Kytario\Service\Auth\AdminServiceInterface;
use Kytario\Service\Auth\PermissionCheckerInterface;
use Kytario\Service\Provider\UserProviderInterface;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use Kytario\Service\Request\RequestServiceInterface;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePatch;
use MarekSkopal\Router\Attribute\RoutePost;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final readonly class AdminWorkspaceController
{
	public function __construct(
		private AdminServiceInterface $adminService,
		private WorkspaceProviderInterface $workspaceProvider,
		private UserProviderInterface $userProvider,
		private PermissionCheckerInterface $permissionChecker,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::AdminWorkspaces->value)]
	public function actionGetWorkspaces(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		if (!$this->permissionChecker->isSystemAdmin($user)) {
			return new NotAuthorizedResponse('System administrator access required.');
		}

		$workspaces = [];
		foreach ($this->adminService->listWorkspaces() as $workspace) {
			$workspaces[] = AdminWorkspaceDto::fromEntity(
				$workspace,
				$this->adminService->countMembers($workspace),
			);
		}

		return new JsonResponse($workspaces);
	}

	#[RouteGet(Routes::AdminWorkspace->value)]
	public function actionGetWorkspace(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		if (!$this->permissionChecker->isSystemAdmin($user)) {
			return new NotAuthorizedResponse('System administrator access required.');
		}

		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		$members = [];
		foreach ($this->workspaceProvider->getMembers($workspace) as $membership) {
			$members[] = WorkspaceMemberDto::fromEntity($membership);
		}

		return new JsonResponse([
			'workspace' => AdminWorkspaceDto::fromEntity($workspace, count($members)),
			'members' => $members,
		]);
	}

	#[RoutePatch(Routes::AdminWorkspace->value)]
	public function actionPatchWorkspace(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		if (!$this->permissionChecker->isSystemAdmin($user)) {
			return new NotAuthorizedResponse('System administrator access required.');
		}

		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, WorkspaceUpdateDto::class);
		$name = $dto->name !== null ? trim($dto->name) : $workspace->name;
		if ($name === '') {
			return new ErrorResponse('Workspace name is required.', 422);
		}

		$updated = $this->workspaceProvider->updateWorkspace($workspace, $name);

		return new JsonResponse(WorkspaceDto::fromEntity($updated));
	}

	#[RouteDelete(Routes::AdminWorkspace->value)]
	public function actionDeleteWorkspace(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		if (!$this->permissionChecker->isSystemAdmin($user)) {
			return new NotAuthorizedResponse('System administrator access required.');
		}

		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		$this->adminService->deleteWorkspace($user, $workspace);

		return new OkResponse();
	}

	#[RoutePost(Routes::AdminWorkspaceMembers->value)]
	public function actionPostMember(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$actor = $this->requestService->getUser($request);
		if (!$this->permissionChecker->isSystemAdmin($actor)) {
			return new NotAuthorizedResponse('System administrator access required.');
		}

		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, AdminAddMemberDto::class);
		$target = $this->userProvider->getUser($dto->userId);
		if ($target === null) {
			return new NotFoundResponse('User not found.');
		}

		$role = WorkspaceRoleEnum::tryFrom($dto->role) ?? WorkspaceRoleEnum::Member;
		if ($role === WorkspaceRoleEnum::Owner) {
			return new ErrorResponse('Use transfer-ownership to set the workspace owner.', 422);
		}

		$membership = $this->workspaceProvider->addMember($workspace, $target, $role);

		return new JsonResponse(WorkspaceMemberDto::fromEntity($membership));
	}

	#[RouteDelete(Routes::AdminWorkspaceMember->value)]
	public function actionDeleteMember(ServerRequestInterface $request, int $workspaceId, int $userId): ResponseInterface
	{
		$actor = $this->requestService->getUser($request);
		if (!$this->permissionChecker->isSystemAdmin($actor)) {
			return new NotAuthorizedResponse('System administrator access required.');
		}

		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		$target = $this->findMembership($workspace, $userId);
		if ($target === null) {
			return new NotFoundResponse('Member not found.');
		}

		if ($target->role === WorkspaceRoleEnum::Owner) {
			return new ErrorResponse('The workspace owner cannot be removed. Transfer ownership first.', 422);
		}

		$this->workspaceProvider->removeMember($target);

		return new OkResponse();
	}

	#[RoutePatch(Routes::AdminWorkspaceTransferOwnership->value)]
	public function actionPatchTransferOwnership(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$actor = $this->requestService->getUser($request);
		if (!$this->permissionChecker->isSystemAdmin($actor)) {
			return new NotAuthorizedResponse('System administrator access required.');
		}

		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, WorkspaceTransferOwnershipDto::class);
		$target = $this->findMembership($workspace, $dto->userId);
		if ($target === null) {
			$user = $this->userProvider->getUser($dto->userId);
			if ($user === null) {
				return new NotFoundResponse('Target user not found.');
			}
			$target = $this->workspaceProvider->addMember($workspace, $user, WorkspaceRoleEnum::Admin);
		}

		try {
			$this->workspaceProvider->transferOwnership($actor, $workspace, $target);
		} catch (RuntimeException $e) {
			return new ConflictResponse($e->getMessage());
		}

		return new JsonResponse(WorkspaceDto::fromEntity($workspace));
	}

	private function findMembership(Workspace $workspace, int $userId): ?WorkspaceUser
	{
		foreach ($this->workspaceProvider->getMembers($workspace) as $membership) {
			if ($membership->user->id === $userId) {
				return $membership;
			}
		}
		return null;
	}
}
