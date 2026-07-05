<?php

declare(strict_types=1);

namespace Kytario\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Kytario\Dto\ScriptCreateDto;
use Kytario\Dto\ScriptDto;
use Kytario\Dto\ScriptRunDto;
use Kytario\Dto\ScriptUpdateDto;
use Kytario\Model\Entity\Enum\ScriptTriggerEnum;
use Kytario\Model\Entity\Script;
use Kytario\Model\Entity\ScriptRun;
use Kytario\Response\ErrorResponse;
use Kytario\Response\NotAuthorizedResponse;
use Kytario\Response\NotFoundResponse;
use Kytario\Response\OkResponse;
use Kytario\Route\Routes;
use Kytario\Service\Auth\PermissionCheckerInterface;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use Kytario\Service\Request\RequestServiceInterface;
use Kytario\Service\Script\ScriptProviderInterface;
use Kytario\Service\Script\ScriptRunDispatcherInterface;

final readonly class ScriptController
{
	private const int MaxRunsLimit = 100;

	public function __construct(
		private ScriptProviderInterface $scriptProvider,
		private ScriptRunDispatcherInterface $scriptRunDispatcher,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::WorkspaceScripts->value)]
	public function actionGetScripts(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}
		if (!$this->permissionChecker->canViewWorkspace($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have access to this workspace.');
		}

		$scripts = array_map(
			fn (Script $script): ScriptDto => ScriptDto::fromEntityWithStats(
				$script,
				$this->scriptProvider->lastStatus($script),
				$this->scriptProvider->runCount($script),
			),
			iterator_to_array($this->scriptProvider->listForWorkspace($workspace), false),
		);

		return new JsonResponse($scripts);
	}

	#[RoutePost(Routes::WorkspaceScripts->value)]
	public function actionPostScript(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}
		if (!$this->permissionChecker->canManageScripts($user, $workspace)) {
			return new NotAuthorizedResponse('Only workspace admins can manage scripts.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, ScriptCreateDto::class);
		$trigger = ScriptTriggerEnum::tryFrom($dto->trigger);
		if ($trigger === null) {
			return new ErrorResponse('Invalid trigger. Expected Manual, Scheduled, or Event.', 422);
		}

		try {
			$script = $this->scriptProvider->create(
				$user,
				$workspace,
				$dto->name,
				$dto->source,
				$trigger,
				$dto->triggerConfig,
				$dto->active,
			);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(ScriptDto::fromEntity($script));
	}

	#[RouteGet(Routes::Script->value)]
	public function actionGetScript(ServerRequestInterface $request, int $scriptId): ResponseInterface
	{
		$script = $this->scriptProvider->getScript($scriptId);
		if ($script === null) {
			return new NotFoundResponse('Script not found.');
		}
		if (!$this->permissionChecker->canViewWorkspace($this->requestService->getUser($request), $script->workspace)) {
			return new NotAuthorizedResponse('You do not have access to this script.');
		}

		return new JsonResponse(ScriptDto::fromEntity($script));
	}

	#[RoutePut(Routes::Script->value)]
	public function actionPutScript(ServerRequestInterface $request, int $scriptId): ResponseInterface
	{
		$script = $this->scriptProvider->getScript($scriptId);
		if ($script === null) {
			return new NotFoundResponse('Script not found.');
		}
		$user = $this->requestService->getUser($request);
		if (!$this->permissionChecker->canManageScripts($user, $script->workspace)) {
			return new NotAuthorizedResponse('Only workspace admins can manage scripts.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, ScriptUpdateDto::class);
		$trigger = ScriptTriggerEnum::tryFrom($dto->trigger);
		if ($trigger === null) {
			return new ErrorResponse('Invalid trigger. Expected Manual, Scheduled, or Event.', 422);
		}

		try {
			$script = $this->scriptProvider->update($user, $script, $dto->name, $dto->source, $trigger, $dto->triggerConfig, $dto->active);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(ScriptDto::fromEntity($script));
	}

	#[RouteDelete(Routes::Script->value)]
	public function actionDeleteScript(ServerRequestInterface $request, int $scriptId): ResponseInterface
	{
		$script = $this->scriptProvider->getScript($scriptId);
		if ($script === null) {
			return new NotFoundResponse('Script not found.');
		}
		$user = $this->requestService->getUser($request);
		if (!$this->permissionChecker->canManageScripts($user, $script->workspace)) {
			return new NotAuthorizedResponse('Only workspace admins can manage scripts.');
		}

		$this->scriptProvider->delete($user, $script);

		return new OkResponse();
	}

	#[RoutePost(Routes::ScriptRunNow->value)]
	public function actionRunScript(ServerRequestInterface $request, int $scriptId): ResponseInterface
	{
		$script = $this->scriptProvider->getScript($scriptId);
		if ($script === null) {
			return new NotFoundResponse('Script not found.');
		}
		if (!$this->permissionChecker->canManageScripts($this->requestService->getUser($request), $script->workspace)) {
			return new NotAuthorizedResponse('Only workspace admins can run scripts.');
		}

		$this->scriptRunDispatcher->dispatch($script, ScriptTriggerEnum::Manual);

		return new JsonResponse(['queued' => true], 202);
	}

	#[RouteGet(Routes::ScriptRuns->value)]
	public function actionGetRuns(ServerRequestInterface $request, int $scriptId): ResponseInterface
	{
		$script = $this->scriptProvider->getScript($scriptId);
		if ($script === null) {
			return new NotFoundResponse('Script not found.');
		}
		// Run logs/errors can contain secret-variable values — restrict to script managers (Admin/Owner),
		// not every workspace member.
		if (!$this->permissionChecker->canManageScripts($this->requestService->getUser($request), $script->workspace)) {
			return new NotAuthorizedResponse('You do not have access to this script.');
		}

		$query = $request->getQueryParams();
		$limit = min(max(self::intParam($query['limit'] ?? null, 25), 1), self::MaxRunsLimit);
		$offset = max(self::intParam($query['offset'] ?? null, 0), 0);

		$runs = array_map(
			static fn (ScriptRun $run): ScriptRunDto => ScriptRunDto::fromEntity($run),
			iterator_to_array($this->scriptProvider->runHistory($script, $limit, $offset), false),
		);

		return new JsonResponse($runs);
	}

	private static function intParam(mixed $value, int $default): int
	{
		return is_numeric($value) ? (int) $value : $default;
	}
}
