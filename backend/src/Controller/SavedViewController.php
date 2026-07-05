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
use Ukolio\Dto\SavedViewCreateDto;
use Ukolio\Dto\SavedViewDto;
use Ukolio\Dto\SavedViewUpdateDto;
use Ukolio\Model\Entity\SavedView;
use Ukolio\Response\ErrorResponse;
use Ukolio\Response\NotAuthorizedResponse;
use Ukolio\Response\NotFoundResponse;
use Ukolio\Response\OkResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Auth\PermissionCheckerInterface;
use Ukolio\Service\Provider\SavedViewProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Request\RequestServiceInterface;

final readonly class SavedViewController
{
	public function __construct(
		private SavedViewProviderInterface $savedViewProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::WorkspaceSavedViews->value)]
	public function actionGetViews(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}
		if (!$this->permissionChecker->canViewWorkspace($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have access to this workspace.');
		}

		$views = array_map(
			static fn (SavedView $view): SavedViewDto => SavedViewDto::fromEntity($view),
			iterator_to_array($this->savedViewProvider->getViews($workspace, $user), false),
		);

		return new JsonResponse($views);
	}

	#[RoutePost(Routes::WorkspaceSavedViews->value)]
	public function actionPostView(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}
		if (!$this->permissionChecker->canViewWorkspace($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have access to this workspace.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, SavedViewCreateDto::class);

		try {
			$view = $this->savedViewProvider->createView($user, $workspace, $dto->name, $dto->filterConfig);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(SavedViewDto::fromEntity($view));
	}

	#[RoutePut(Routes::SavedView->value)]
	public function actionPutView(ServerRequestInterface $request, int $savedViewId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$view = $this->savedViewProvider->getViewForUser($savedViewId, $user);
		if ($view === null) {
			return new NotFoundResponse('Saved view not found.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, SavedViewUpdateDto::class);

		try {
			$view = $this->savedViewProvider->updateView($view, $dto->name, $dto->filterConfig);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(SavedViewDto::fromEntity($view));
	}

	#[RouteDelete(Routes::SavedView->value)]
	public function actionDeleteView(ServerRequestInterface $request, int $savedViewId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$view = $this->savedViewProvider->getViewForUser($savedViewId, $user);
		if ($view === null) {
			return new NotFoundResponse('Saved view not found.');
		}

		$this->savedViewProvider->deleteView($view);

		return new OkResponse();
	}
}
