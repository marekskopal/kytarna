<?php

declare(strict_types=1);

namespace Kytarna\Controller;

use Kytarna\Dto\LectureBulkRequestDto;
use Kytarna\Response\ErrorResponse;
use Kytarna\Route\Routes;
use Kytarna\Service\Auth\PermissionCheckerInterface;
use Kytarna\Service\Provider\BulkLectureProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Kytarna\Service\Request\RequestServiceInterface;
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
