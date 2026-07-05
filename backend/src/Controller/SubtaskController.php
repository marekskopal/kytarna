<?php

declare(strict_types=1);

namespace Kytario\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Kytario\Dto\SubtaskCreateDto;
use Kytario\Dto\SubtaskDto;
use Kytario\Model\Entity\TaskRelation;
use Kytario\Response\ErrorResponse;
use Kytario\Response\NotFoundResponse;
use Kytario\Route\Routes;
use Kytario\Service\Provider\SubtaskProviderInterface;
use Kytario\Service\Provider\TaskCodeResolverInterface;
use Kytario\Service\Request\RequestServiceInterface;

final readonly class SubtaskController
{
	public function __construct(
		private SubtaskProviderInterface $subtaskProvider,
		private TaskCodeResolverInterface $taskCodeResolver,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::TaskSubtasks->value)]
	public function actionGetSubtasks(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$parent = $this->taskCodeResolver->resolveForUser($user, (string) $taskId);
		if ($parent === null) {
			return new NotFoundResponse('Task not found.');
		}

		$toggleIdsByProject = [];
		$subtasks = [];
		foreach ($this->subtaskProvider->getSubtaskRelations($parent) as $relation) {
			$projectId = $relation->targetTask->project->id;
			$toggleIdsByProject[$projectId] ??= $this->subtaskProvider->getToggleStatusIds($relation->targetTask->project);
			$toggleIds = $toggleIdsByProject[$projectId];
			$subtasks[] = SubtaskDto::fromRelation($relation, $toggleIds['startStatusId'], $toggleIds['finishStatusId']);
		}

		return new JsonResponse($subtasks);
	}

	#[RoutePost(Routes::TaskSubtasks->value)]
	public function actionPostSubtask(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$parent = $this->taskCodeResolver->resolveForUser($user, (string) $taskId);
		if ($parent === null) {
			return new NotFoundResponse('Task not found.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, SubtaskCreateDto::class);
		if (trim($dto->name) === '') {
			return new ErrorResponse('Subtask name cannot be empty.', 422);
		}

		try {
			$relation = $this->subtaskProvider->createSubtask($user, $parent, trim($dto->name));
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse($this->toDto($relation), 201);
	}

	private function toDto(TaskRelation $relation): SubtaskDto
	{
		$toggleIds = $this->subtaskProvider->getToggleStatusIds($relation->targetTask->project);

		return SubtaskDto::fromRelation($relation, $toggleIds['startStatusId'], $toggleIds['finishStatusId']);
	}
}
