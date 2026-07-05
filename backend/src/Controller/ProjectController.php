<?php

declare(strict_types=1);

namespace Kytario\Controller;

use Kytario\Dto\ProjectCreateDto;
use Kytario\Dto\ProjectDto;
use Kytario\Dto\ProjectUpdateDto;
use Kytario\Model\Entity\Project;
use Kytario\Response\ErrorResponse;
use Kytario\Response\NotAuthorizedResponse;
use Kytario\Response\NotFoundResponse;
use Kytario\Response\OkResponse;
use Kytario\Route\Routes;
use Kytario\Service\Auth\PermissionCheckerInterface;
use Kytario\Service\Provider\ProjectProviderInterface;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use Kytario\Service\Request\RequestServiceInterface;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final readonly class ProjectController
{
	public function __construct(
		private ProjectProviderInterface $projectProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::Projects->value)]
	public function actionGetProjects(ServerRequestInterface $request): ResponseInterface
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->requestService->getUser($request));
		if ($workspace === null) {
			return new JsonResponse([]);
		}

		$projects = array_map(
			fn (Project $p): ProjectDto => ProjectDto::fromEntity($p),
			iterator_to_array($this->projectProvider->getProjects($workspace), false),
		);

		return new JsonResponse($projects);
	}

	#[RouteGet(Routes::Project->value)]
	public function actionGetProject(ServerRequestInterface $request, int $projectId): ResponseInterface
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->requestService->getUser($request));
		if ($workspace === null) {
			return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
		}

		$project = $this->projectProvider->getProject($workspace, $projectId);
		if ($project === null) {
			return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
		}

		return new JsonResponse(ProjectDto::fromEntity($project));
	}

	#[RoutePost(Routes::Projects->value)]
	public function actionPostProject(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);
		if ($workspace === null) {
			return new ErrorResponse('No active workspace.', 422);
		}

		if (!$this->permissionChecker->canManageProjects($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have permission to create projects.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, ProjectCreateDto::class);

		try {
			$project = $this->projectProvider->createProject($user, $workspace, $dto->name, $dto->description);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(ProjectDto::fromEntity($project));
	}

	#[RoutePut(Routes::Project->value)]
	public function actionPutProject(ServerRequestInterface $request, int $projectId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);
		if ($workspace === null) {
			return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
		}

		$project = $this->projectProvider->getProject($workspace, $projectId);
		if ($project === null) {
			return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
		}

		if (!$this->permissionChecker->canManageProjects($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have permission to update projects.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, ProjectUpdateDto::class);

		try {
			$project = $this->projectProvider->updateProject(
				author: $user,
				project: $project,
				name: $dto->name ?? $project->name,
				description: $dto->description ?? $project->description,
			);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(ProjectDto::fromEntity($project));
	}

	#[RouteDelete(Routes::Project->value)]
	public function actionDeleteProject(ServerRequestInterface $request, int $projectId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);
		if ($workspace === null) {
			return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
		}

		$project = $this->projectProvider->getProject($workspace, $projectId);
		if ($project === null) {
			return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
		}

		if (!$this->permissionChecker->canManageProjects($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have permission to delete projects.');
		}

		$this->projectProvider->deleteProject($project);

		return new OkResponse();
	}
}
