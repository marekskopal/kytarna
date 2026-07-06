<?php

declare(strict_types=1);

namespace Kytarna\Controller\Admin;

use Kytarna\Dto\AdminAddMemberDto;
use Kytarna\Dto\AdminWorkspaceDto;
use Kytarna\Dto\WorkspaceDto;
use Kytarna\Dto\WorkspaceMemberDto;
use Kytarna\Dto\WorkspaceUpdateDto;
use Kytarna\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Model\Entity\WorkspaceUser;
use Kytarna\Response\ErrorResponse;
use Kytarna\Response\NotAuthorizedResponse;
use Kytarna\Response\NotFoundResponse;
use Kytarna\Response\OkResponse;
use Kytarna\Route\Routes;
use Kytarna\Service\Auth\AdminServiceInterface;
use Kytarna\Service\Auth\PermissionCheckerInterface;
use Kytarna\Service\Provider\UserProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Kytarna\Service\Request\RequestServiceInterface;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePatch;
use MarekSkopal\Router\Attribute\RoutePost;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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

		// Admins can only add Students; the sole Teacher is the workspace owner.
		$membership = $this->workspaceProvider->addMember($workspace, $target, WorkspaceRoleEnum::Student);

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

		if ($target->role === WorkspaceRoleEnum::Teacher) {
			return new ErrorResponse('The workspace teacher cannot be removed. Delete the workspace instead.', 422);
		}

		$this->workspaceProvider->removeMember($target);

		return new OkResponse();
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
