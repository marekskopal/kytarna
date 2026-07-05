<?php

declare(strict_types=1);

namespace Ukolio\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePatch;
use MarekSkopal\Router\Attribute\RoutePost;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Ukolio\Dto\WorkspaceCreateDto;
use Ukolio\Dto\WorkspaceDto;
use Ukolio\Dto\WorkspaceMemberDto;
use Ukolio\Dto\WorkspaceMemberRoleUpdateDto;
use Ukolio\Dto\WorkspaceTransferOwnershipDto;
use Ukolio\Dto\WorkspaceUpdateDto;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Entity\WorkspaceUser;
use Ukolio\Response\ErrorResponse;
use Ukolio\Response\NotAuthorizedResponse;
use Ukolio\Response\NotFoundResponse;
use Ukolio\Response\OkResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Auth\PermissionCheckerInterface;
use Ukolio\Service\Provider\WorkspaceMcpClientProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Realtime\MercureCookieIssuerInterface;
use Ukolio\Service\Request\RequestServiceInterface;

final readonly class WorkspaceController
{
	public function __construct(
		private WorkspaceProviderInterface $workspaceProvider,
		private WorkspaceMcpClientProviderInterface $mcpClientProvider,
		private PermissionCheckerInterface $permissionChecker,
		private RequestServiceInterface $requestService,
		private MercureCookieIssuerInterface $mercureCookieIssuer,
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

		$response = new JsonResponse(WorkspaceDto::fromEntity($workspace));

		return $response->withAddedHeader(
			'Set-Cookie',
			$this->mercureCookieIssuer->issue($user, $this->isSecureRequest($request)),
		);
	}

	private function isSecureRequest(ServerRequestInterface $request): bool
	{
		$forwardedProto = $request->getHeader('X-Forwarded-Proto')[0] ?? null;
		if ($forwardedProto !== null) {
			return strtolower($forwardedProto) === 'https';
		}

		return strtolower($request->getUri()->getScheme()) === 'https';
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
