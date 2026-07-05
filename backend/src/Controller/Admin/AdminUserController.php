<?php

declare(strict_types=1);

namespace Ukolio\Controller\Admin;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePatch;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Ukolio\Dto\AdminUserDto;
use Ukolio\Dto\AdminUserUpdateDto;
use Ukolio\Model\Entity\Enum\SystemRoleEnum;
use Ukolio\Response\ConflictResponse;
use Ukolio\Response\NotAuthorizedResponse;
use Ukolio\Response\NotFoundResponse;
use Ukolio\Response\OkResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Auth\AdminServiceInterface;
use Ukolio\Service\Auth\PermissionCheckerInterface;
use Ukolio\Service\Provider\UserProviderInterface;
use Ukolio\Service\Request\RequestServiceInterface;

final readonly class AdminUserController
{
	public function __construct(
		private AdminServiceInterface $adminService,
		private UserProviderInterface $userProvider,
		private PermissionCheckerInterface $permissionChecker,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::AdminUsers->value)]
	public function actionGetUsers(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		if (!$this->permissionChecker->isSystemAdmin($user)) {
			return new NotAuthorizedResponse('System administrator access required.');
		}

		$users = [];
		foreach ($this->adminService->listUsers() as $candidate) {
			$users[] = AdminUserDto::fromEntity(
				$candidate,
				$this->adminService->countWorkspacesForUser($candidate),
				$this->adminService->countOwnedWorkspaces($candidate),
			);
		}

		return new JsonResponse($users);
	}

	#[RoutePatch(Routes::AdminUser->value)]
	public function actionPatchUser(ServerRequestInterface $request, int $userId): ResponseInterface
	{
		$actor = $this->requestService->getUser($request);
		if (!$this->permissionChecker->isSystemAdmin($actor)) {
			return new NotAuthorizedResponse('System administrator access required.');
		}

		$target = $this->userProvider->getUser($userId);
		if ($target === null) {
			return new NotFoundResponse('User not found.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, AdminUserUpdateDto::class);
		$systemRole = $dto->systemRole !== null ? SystemRoleEnum::tryFrom($dto->systemRole) : null;

		try {
			$updated = $this->adminService->updateUser($actor, $target, $dto->name, $dto->email, $systemRole);
		} catch (RuntimeException $e) {
			return new ConflictResponse($e->getMessage());
		}

		return new JsonResponse(AdminUserDto::fromEntity(
			$updated,
			$this->adminService->countWorkspacesForUser($updated),
			$this->adminService->countOwnedWorkspaces($updated),
		));
	}

	#[RouteDelete(Routes::AdminUser->value)]
	public function actionDeleteUser(ServerRequestInterface $request, int $userId): ResponseInterface
	{
		$actor = $this->requestService->getUser($request);
		if (!$this->permissionChecker->isSystemAdmin($actor)) {
			return new NotAuthorizedResponse('System administrator access required.');
		}

		$target = $this->userProvider->getUser($userId);
		if ($target === null) {
			return new NotFoundResponse('User not found.');
		}

		try {
			$this->adminService->deleteUser($actor, $target);
		} catch (RuntimeException $e) {
			return new ConflictResponse($e->getMessage());
		}

		return new OkResponse();
	}
}
