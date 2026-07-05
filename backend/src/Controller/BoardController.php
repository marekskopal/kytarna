<?php

declare(strict_types=1);

namespace Ukolio\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteGet;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ukolio\Dto\BoardDto;
use Ukolio\Dto\ProjectDto;
use Ukolio\Dto\StatusDto;
use Ukolio\Dto\TaskDto;
use Ukolio\Dto\WorkflowDto;
use Ukolio\Model\Entity\Status;
use Ukolio\Model\Entity\Task;
use Ukolio\Response\NotFoundResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Provider\ProjectProviderInterface;
use Ukolio\Service\Provider\StatusProviderInterface;
use Ukolio\Service\Provider\SubtaskProviderInterface;
use Ukolio\Service\Provider\TaskChecklistProviderInterface;
use Ukolio\Service\Provider\TaskProviderInterface;
use Ukolio\Service\Provider\TaskTagProviderInterface;
use Ukolio\Service\Provider\WorkflowProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Request\RequestServiceInterface;

final readonly class BoardController
{
	public function __construct(
		private ProjectProviderInterface $projectProvider,
		private WorkflowProviderInterface $workflowProvider,
		private StatusProviderInterface $statusProvider,
		private TaskProviderInterface $taskProvider,
		private TaskTagProviderInterface $taskTagProvider,
		private SubtaskProviderInterface $subtaskProvider,
		private TaskChecklistProviderInterface $checklistProvider,
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
		$subtaskCounts = $this->subtaskProvider->getSubtaskCounts($taskIds);
		$checklistCounts = $this->checklistProvider->getCounts($taskIds);
		$tasks = array_map(
			fn (Task $t): TaskDto => TaskDto::fromEntity(
				$t,
				[],
				$tagsByTaskId[$t->id] ?? [],
				$subtaskCounts[$t->id]['total'] ?? 0,
				$subtaskCounts[$t->id]['done'] ?? 0,
				$checklistCounts[$t->id]['total'] ?? 0,
				$checklistCounts[$t->id]['done'] ?? 0,
			),
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
