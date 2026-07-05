<?php

declare(strict_types=1);

namespace Kytario\Controller;

use Kytario\Dto\BoardDto;
use Kytario\Dto\ProjectDto;
use Kytario\Dto\StatusDto;
use Kytario\Dto\TaskDto;
use Kytario\Dto\WorkflowDto;
use Kytario\Model\Entity\Status;
use Kytario\Model\Entity\Task;
use Kytario\Response\NotFoundResponse;
use Kytario\Route\Routes;
use Kytario\Service\Provider\ProjectProviderInterface;
use Kytario\Service\Provider\StatusProviderInterface;
use Kytario\Service\Provider\TaskProviderInterface;
use Kytario\Service\Provider\TaskTagProviderInterface;
use Kytario\Service\Provider\WorkflowProviderInterface;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use Kytario\Service\Request\RequestServiceInterface;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteGet;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class BoardController
{
	public function __construct(
		private ProjectProviderInterface $projectProvider,
		private WorkflowProviderInterface $workflowProvider,
		private StatusProviderInterface $statusProvider,
		private TaskProviderInterface $taskProvider,
		private TaskTagProviderInterface $taskTagProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::ProjectBoard->value)]
	public function actionGetBoard(ServerRequestInterface $request, int $projectId): ResponseInterface
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

		$statuses = array_map(
			fn (Status $s): StatusDto => StatusDto::fromEntity($s),
			iterator_to_array($this->statusProvider->getStatuses($workflow), false),
		);

		$projectTasks = iterator_to_array($this->taskProvider->getTasksByProject($project, includeArchived: false), false);
		$taskIds = array_map(static fn (Task $t): int => $t->id, $projectTasks);
		$tagsByTaskId = $this->taskTagProvider->getTagIdsByTaskIds($taskIds);
		$tasks = array_map(
			static fn (Task $t): TaskDto => TaskDto::fromEntity($t, $tagsByTaskId[$t->id] ?? []),
			$projectTasks,
		);

		return new JsonResponse(new BoardDto(
			project: ProjectDto::fromEntity($project),
			workflow: WorkflowDto::fromEntity($workflow),
			statuses: $statuses,
			tasks: $tasks,
		));
	}
}
