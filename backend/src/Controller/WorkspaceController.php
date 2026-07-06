<?php

declare(strict_types=1);

namespace Kytarna\Controller;

use Kytarna\Dto\PublicWorkspaceDto;
use Kytarna\Dto\WorkspaceCreateDto;
use Kytarna\Dto\WorkspaceDto;
use Kytarna\Dto\WorkspaceJoinDto;
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
use Kytarna\Service\Auth\PermissionCheckerInterface;
use Kytarna\Service\Provider\WorkspaceMcpClientProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Kytarna\Service\Request\RequestServiceInterface;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use function is_numeric;
use function is_string;
use function iterator_count;
use function max;
use function min;

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
			$workspace = $membership->workspace;
			$workspaces[] = WorkspaceDto::fromEntity($workspace, includeJoinCode: $workspace->owner->id === $user->id);
		}

		return new JsonResponse($workspaces);
	}

	#[RouteGet(Routes::WorkspaceDiscover->value)]
	public function actionGetDiscover(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$params = $request->getQueryParams();

		$searchRaw = $params['search'] ?? null;
		$search = is_string($searchRaw) && $searchRaw !== '' ? $searchRaw : null;

		$limitRaw = $params['limit'] ?? null;
		$limit = min(max(is_numeric($limitRaw) ? (int) $limitRaw : 20, 1), 100);

		$offsetRaw = $params['offset'] ?? null;
		$offset = max(is_numeric($offsetRaw) ? (int) $offsetRaw : 0, 0);

		$workspaces = [];
		foreach ($this->workspaceProvider->findPublicWorkspaces($user, $search, $limit, $offset) as $workspace) {
			$memberCount = iterator_count($this->workspaceProvider->getMembers($workspace));
			$workspaces[] = PublicWorkspaceDto::fromEntity($workspace, $memberCount);
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
			$updated = $this->workspaceProvider->updateWorkspace(
				$workspace,
				$dto->name ?? $workspace->name,
				$dto->isPublic,
				$dto->description,
			);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(WorkspaceDto::fromEntity($updated, includeJoinCode: true));
	}

	#[RoutePost(Routes::WorkspaceRotateJoinCode->value)]
	public function actionPostRotateJoinCode(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		if (!$this->permissionChecker->canManageWorkspace($user, $workspace)) {
			return new NotAuthorizedResponse('Only the teacher can rotate the join code.');
		}

		$this->workspaceProvider->rotateJoinCode($workspace);

		return new JsonResponse(WorkspaceDto::fromEntity($workspace, includeJoinCode: true));
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

	#[RoutePost(Routes::WorkspaceJoin->value)]
	public function actionPostJoin(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null || !$workspace->isPublic) {
			return new NotFoundResponse('Workspace not found.');
		}

		$this->workspaceProvider->joinAsStudent($user, $workspace);

		return new JsonResponse(WorkspaceDto::fromEntity($workspace));
	}

	#[RoutePost(Routes::WorkspaceJoinByCode->value)]
	public function actionPostJoinByCode(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$dto = $this->requestService->getRequestBodyDto($request, WorkspaceJoinDto::class);

		$workspace = $this->workspaceProvider->findByJoinCode($dto->code);
		if ($workspace === null) {
			return new NotFoundResponse('No workspace matches that join code.');
		}

		$this->workspaceProvider->joinAsStudent($user, $workspace);

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

		if ($target->role === WorkspaceRoleEnum::Teacher) {
			return new ErrorResponse('The teacher cannot be removed. Delete the workspace instead.', 422);
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
