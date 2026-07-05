<?php

declare(strict_types=1);

namespace Kytario\Controller;

use Kytario\Dto\LectureBulkRequestDto;
use Kytario\Response\ErrorResponse;
use Kytario\Route\Routes;
use Kytario\Service\Auth\PermissionCheckerInterface;
use Kytario\Service\Provider\BulkLectureProviderInterface;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use Kytario\Service\Request\RequestServiceInterface;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RoutePost;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final readonly class LectureBulkController
{
	public function __construct(
		private RequestServiceInterface $requestService,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
		private BulkLectureProviderInterface $bulkLectureProvider,
	) {
	}

	#[RoutePost(Routes::LecturesBulk->value)]
	public function actionPostLecturesBulk(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);
		if ($workspace === null) {
			return new ErrorResponse('No active workspace.', 422);
		}

		if (!$this->permissionChecker->canManageLectures($user, $workspace)) {
			return new ErrorResponse('You do not have permission to manage lectures.', 403);
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, LectureBulkRequestDto::class);
			$result = $this->bulkLectureProvider->execute($user, $workspace, $dto->op, $dto->ids, $dto->payload);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse($result);
	}
}
