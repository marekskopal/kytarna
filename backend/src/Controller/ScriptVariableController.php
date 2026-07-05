<?php

declare(strict_types=1);

namespace Kytario\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Kytario\Dto\ScriptVariableDto;
use Kytario\Dto\ScriptVariableUpsertDto;
use Kytario\Model\Entity\ScriptVariable;
use Kytario\Response\ErrorResponse;
use Kytario\Response\NotAuthorizedResponse;
use Kytario\Response\NotFoundResponse;
use Kytario\Response\OkResponse;
use Kytario\Route\Routes;
use Kytario\Service\Auth\PermissionCheckerInterface;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use Kytario\Service\Request\RequestServiceInterface;
use Kytario\Service\Script\ScriptVariableProviderInterface;

final readonly class ScriptVariableController
{
	public function __construct(
		private ScriptVariableProviderInterface $variableProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::WorkspaceScriptVariables->value)]
	public function actionGetVariables(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}
		if (!$this->permissionChecker->canManageScripts($user, $workspace)) {
			return new NotAuthorizedResponse('Only workspace admins can manage script variables.');
		}

		$variables = array_map(
			static fn (ScriptVariable $variable): ScriptVariableDto => ScriptVariableDto::fromEntity($variable),
			iterator_to_array($this->variableProvider->listForWorkspace($workspace), false),
		);

		return new JsonResponse($variables);
	}

	#[RoutePut(Routes::WorkspaceScriptVariables->value)]
	public function actionPutVariable(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}
		if (!$this->permissionChecker->canManageScripts($user, $workspace)) {
			return new NotAuthorizedResponse('Only workspace admins can manage script variables.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, ScriptVariableUpsertDto::class);

		try {
			$variable = $this->variableProvider->set($workspace, $dto->key, $dto->value, $dto->isSecret);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(ScriptVariableDto::fromEntity($variable));
	}

	#[RouteDelete(Routes::WorkspaceScriptVariable->value)]
	public function actionDeleteVariable(ServerRequestInterface $request, int $workspaceId, int $variableId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}
		if (!$this->permissionChecker->canManageScripts($user, $workspace)) {
			return new NotAuthorizedResponse('Only workspace admins can manage script variables.');
		}

		$variable = $this->variableProvider->getById($workspace, $variableId);
		if ($variable === null) {
			return new NotFoundResponse('Variable not found.');
		}

		$this->variableProvider->delete($variable);

		return new OkResponse();
	}
}
