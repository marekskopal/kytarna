<?php

declare(strict_types=1);

namespace Kytarna\Controller;

use Kytarna\Dto\WorkflowDto;
use Kytarna\Dto\WorkflowUpdateDto;
use Kytarna\Dto\WorkflowWithStatusesDto;
use Kytarna\Response\ErrorResponse;
use Kytarna\Response\NotAuthorizedResponse;
use Kytarna\Response\NotFoundResponse;
use Kytarna\Route\Routes;
use Kytarna\Service\Auth\PermissionCheckerInterface;
use Kytarna\Service\Provider\CourseProviderInterface;
use Kytarna\Service\Provider\StatusProviderInterface;
use Kytarna\Service\Provider\WorkflowProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Kytarna\Service\Request\RequestServiceInterface;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class WorkflowController
{
	public function __construct(
		private CourseProviderInterface $courseProvider,
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

	#[RouteGet(Routes::CourseWorkflow->value)]
	public function actionGetWorkflow(ServerRequestInterface $request, int $courseId): ResponseInterface
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->requestService->getUser($request));
		if ($workspace === null) {
			return new NotFoundResponse('Course with id "' . $courseId . '" was not found.');
		}

		$course = $this->courseProvider->getCourse($workspace, $courseId);
		if ($course === null) {
			return new NotFoundResponse('Course with id "' . $courseId . '" was not found.');
		}

		$workflow = $this->workflowProvider->getWorkflowByCourse($course);
		if ($workflow === null) {
			return new NotFoundResponse('Course has no workflow.');
		}

		return new JsonResponse(WorkflowDto::fromEntity($workflow));
	}

	#[RoutePut(Routes::CourseWorkflow->value)]
	public function actionPutWorkflow(ServerRequestInterface $request, int $courseId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);
		if ($workspace === null) {
			return new NotFoundResponse('Course with id "' . $courseId . '" was not found.');
		}

		$course = $this->courseProvider->getCourse($workspace, $courseId);
		if ($course === null) {
			return new NotFoundResponse('Course with id "' . $courseId . '" was not found.');
		}

		if (!$this->permissionChecker->canManageCourses($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have permission to update the workflow.');
		}

		$workflow = $this->workflowProvider->getWorkflowByCourse($course);
		if ($workflow === null) {
			return new NotFoundResponse('Course has no workflow.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, WorkflowUpdateDto::class);
		$workflow = $this->workflowProvider->updateWorkflow($workflow, $dto->name);

		return new JsonResponse(WorkflowDto::fromEntity($workflow));
	}
}
