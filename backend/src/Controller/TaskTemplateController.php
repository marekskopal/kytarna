<?php

declare(strict_types=1);

namespace Kytario\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Kytario\Dto\TaskTemplateDto;
use Kytario\Dto\TaskTemplateSaveDto;
use Kytario\Model\Entity\TaskTemplate;
use Kytario\Response\ErrorResponse;
use Kytario\Response\NotAuthorizedResponse;
use Kytario\Response\NotFoundResponse;
use Kytario\Response\OkResponse;
use Kytario\Route\Routes;
use Kytario\Service\Auth\PermissionCheckerInterface;
use Kytario\Service\Provider\TaskCodeResolverInterface;
use Kytario\Service\Provider\TaskTemplateProviderInterface;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use Kytario\Service\Request\RequestServiceInterface;

final readonly class TaskTemplateController
{
	public function __construct(
		private TaskTemplateProviderInterface $taskTemplateProvider,
		private TaskCodeResolverInterface $taskCodeResolver,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::WorkspaceTaskTemplates->value)]
	public function actionGetTemplates(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}
		if (!$this->permissionChecker->canViewWorkspace($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have access to this workspace.');
		}

		$templates = array_map(
			static fn (TaskTemplate $template): TaskTemplateDto => TaskTemplateDto::fromEntity($template),
			iterator_to_array($this->taskTemplateProvider->getTemplates($workspace), false),
		);

		return new JsonResponse($templates);
	}

	#[RoutePost(Routes::TaskSaveAsTemplate->value)]
	public function actionPostSaveAsTemplate(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->taskCodeResolver->resolveForUser($user, (string) $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}

		if (!$this->permissionChecker->canManageTaskTemplates($user, $task->project->workspace)) {
			return new NotAuthorizedResponse('You do not have permission to manage task templates.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, TaskTemplateSaveDto::class);

		try {
			$template = $this->taskTemplateProvider->createFromTask($task, $dto->name);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(TaskTemplateDto::fromEntity($template));
	}

	#[RouteDelete(Routes::TaskTemplate->value)]
	public function actionDeleteTemplate(ServerRequestInterface $request, int $taskTemplateId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$template = $this->taskTemplateProvider->getTemplateById($taskTemplateId);
		if ($template === null || !$this->permissionChecker->canViewWorkspace($user, $template->workspace)) {
			return new NotFoundResponse('Task template not found.');
		}

		if (!$this->permissionChecker->canManageTaskTemplates($user, $template->workspace)) {
			return new NotAuthorizedResponse('You do not have permission to manage task templates.');
		}

		$this->taskTemplateProvider->deleteTemplate($template);

		return new OkResponse();
	}
}
