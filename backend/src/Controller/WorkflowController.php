<?php

declare(strict_types=1);

namespace Kytario\Controller;

use Kytario\Dto\WorkflowDto;
use Kytario\Dto\WorkflowUpdateDto;
use Kytario\Dto\WorkflowWithStatusesDto;
use Kytario\Response\ErrorResponse;
use Kytario\Response\NotAuthorizedResponse;
use Kytario\Response\NotFoundResponse;
use Kytario\Route\Routes;
use Kytario\Service\Auth\PermissionCheckerInterface;
use Kytario\Service\Provider\ProjectProviderInterface;
use Kytario\Service\Provider\StatusProviderInterface;
use Kytario\Service\Provider\WorkflowProviderInterface;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use Kytario\Service\Request\RequestServiceInterface;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class WorkflowController
{
	public function __construct(
		private ProjectProviderInterface $projectProvider,
		private WorkflowProviderInterface $workflowProvider,
		private StatusProviderInterface $statusProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::Workflows->value)]
	public function actionGetWorkflows(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);
		if ($workspace === null) {
			return new ErrorResponse('No active workspace.', 422);
		}

		$workflows = [];
		foreach ($this->workflowProvider->getWorkflowsInWorkspace($workspace) as $workflow) {
			$workflows[] = WorkflowWithStatusesDto::fromEntity(
				$workflow,
				$this->statusProvider->getStatuses($workflow),
			);
		}

		return new JsonResponse($workflows);
	}

	#[RouteGet(Routes::ProjectWorkflow->value)]
	public function actionGetWorkflow(ServerRequestInterface $request, int $projectId): ResponseInterface
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->requestService->getUser($request));
		if ($workspace === null) {
			return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
		}

		$project = $this->projectProvider->getProject($workspace, $projectId);
		if ($project === null) {
			return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
		}

		$workflow = $this->workflowProvider->getWorkflowByProject($project);
		if ($workflow === null) {
			return new NotFoundResponse('Project has no workflow.');
		}

		return new JsonResponse(WorkflowDto::fromEntity($workflow));
	}

	#[RoutePut(Routes::ProjectWorkflow->value)]
	public function actionPutWorkflow(ServerRequestInterface $request, int $projectId): ResponseInterface
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
			return new NotAuthorizedResponse('You do not have permission to update the workflow.');
		}

		$workflow = $this->workflowProvider->getWorkflowByProject($project);
		if ($workflow === null) {
			return new NotFoundResponse('Project has no workflow.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, WorkflowUpdateDto::class);
		$workflow = $this->workflowProvider->updateWorkflow($workflow, $dto->name);

		return new JsonResponse(WorkflowDto::fromEntity($workflow));
	}
}
