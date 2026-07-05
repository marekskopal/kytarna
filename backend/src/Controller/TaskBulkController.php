<?php

declare(strict_types=1);

namespace Ukolio\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RoutePost;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Ukolio\Dto\TaskBulkRequestDto;
use Ukolio\Response\ErrorResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Auth\PermissionCheckerInterface;
use Ukolio\Service\Provider\BulkTaskProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Request\RequestServiceInterface;

final readonly class TaskBulkController
{
	public function __construct(
		private RequestServiceInterface $requestService,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
		private BulkTaskProviderInterface $bulkTaskProvider,
	) {
	}

	#[RoutePost(Routes::TasksBulk->value)]
	public function actionPostTasksBulk(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);
		if ($workspace === null) {
			return new ErrorResponse('No active workspace.', 422);
		}

		if (!$this->permissionChecker->canManageTasks($user, $workspace)) {
			return new ErrorResponse('You do not have permission to manage tasks.', 403);
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, TaskBulkRequestDto::class);
			$result = $this->bulkTaskProvider->execute($user, $workspace, $dto->op, $dto->ids, $dto->payload);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse($result);
	}
}
