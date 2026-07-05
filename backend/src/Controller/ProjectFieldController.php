<?php

declare(strict_types=1);

namespace Kytario\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Kytario\Dto\ProjectFieldDto;
use Kytario\Dto\ProjectFieldsUpdateDto;
use Kytario\Model\Entity\ProjectField;
use Kytario\Response\ErrorResponse;
use Kytario\Response\NotAuthorizedResponse;
use Kytario\Response\NotFoundResponse;
use Kytario\Route\Routes;
use Kytario\Service\Auth\PermissionCheckerInterface;
use Kytario\Service\Provider\ProjectFieldProviderInterface;
use Kytario\Service\Provider\ProjectProviderInterface;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use Kytario\Service\Request\RequestServiceInterface;

final readonly class ProjectFieldController
{
	public function __construct(
		private ProjectFieldProviderInterface $projectFieldProvider,
		private ProjectProviderInterface $projectProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::ProjectFields->value)]
	public function actionGetProjectFields(ServerRequestInterface $request, int $projectId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);
		if ($workspace === null) {
			return new NotFoundResponse('Project not found.');
		}
		$project = $this->projectProvider->getProject($workspace, $projectId);
		if ($project === null) {
			return new NotFoundResponse('Project not found.');
		}
		if (!$this->permissionChecker->canViewWorkspace($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have access to this project.');
		}

		$dtos = array_map(
			fn (ProjectField $pf): ProjectFieldDto => ProjectFieldDto::fromEntity($pf),
			$this->projectFieldProvider->getProjectFields($project),
		);

		return new JsonResponse($dtos);
	}

	#[RoutePut(Routes::ProjectFields->value)]
	public function actionPutProjectFields(ServerRequestInterface $request, int $projectId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);
		if ($workspace === null) {
			return new NotFoundResponse('Project not found.');
		}
		$project = $this->projectProvider->getProject($workspace, $projectId);
		if ($project === null) {
			return new NotFoundResponse('Project not found.');
		}
		if (!$this->permissionChecker->canManageProjects($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have permission to manage this project.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, ProjectFieldsUpdateDto::class);

		try {
			$this->projectFieldProvider->setProjectFields($user, $project, $dto->fieldIds);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		$dtos = array_map(
			fn (ProjectField $pf): ProjectFieldDto => ProjectFieldDto::fromEntity($pf),
			$this->projectFieldProvider->getProjectFields($project),
		);

		return new JsonResponse($dtos);
	}
}
