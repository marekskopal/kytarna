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
use Kytario\Dto\FieldCreateDto;
use Kytario\Dto\FieldDto;
use Kytario\Dto\FieldUpdateDto;
use Kytario\Model\Entity\Field;
use Kytario\Response\ErrorResponse;
use Kytario\Response\NotAuthorizedResponse;
use Kytario\Response\NotFoundResponse;
use Kytario\Response\OkResponse;
use Kytario\Route\Routes;
use Kytario\Service\Auth\PermissionCheckerInterface;
use Kytario\Service\Provider\FieldProviderInterface;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use Kytario\Service\Request\RequestServiceInterface;

final readonly class FieldController
{
	public function __construct(
		private FieldProviderInterface $fieldProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::WorkspaceFields->value)]
	public function actionGetFields(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}
		if (!$this->permissionChecker->canViewWorkspace($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have access to this workspace.');
		}

		$fields = array_map(
			fn (Field $field): FieldDto => FieldDto::fromEntity($field),
			iterator_to_array($this->fieldProvider->getFields($workspace), false),
		);

		return new JsonResponse($fields);
	}

	#[RoutePost(Routes::WorkspaceFields->value)]
	public function actionPostField(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}
		if (!$this->permissionChecker->canManageFields($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have permission to manage fields.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, FieldCreateDto::class);

		try {
			$field = $this->fieldProvider->createField(
				author: $user,
				workspace: $workspace,
				name: $dto->name,
				type: $dto->type,
				required: $dto->required,
				defaultValue: $dto->defaultValue,
				options: $dto->options,
			);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(FieldDto::fromEntity($field));
	}

	#[RoutePut(Routes::WorkspaceField->value)]
	public function actionPutField(ServerRequestInterface $request, int $workspaceId, int $fieldId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}
		if (!$this->permissionChecker->canManageFields($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have permission to manage fields.');
		}

		$field = $this->fieldProvider->getField($workspace, $fieldId);
		if ($field === null) {
			return new NotFoundResponse('Field not found.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, FieldUpdateDto::class);

		try {
			$field = $this->fieldProvider->updateField(
				author: $user,
				field: $field,
				name: $dto->name,
				type: $dto->type,
				required: $dto->required,
				defaultValue: $dto->defaultValue,
				options: $dto->options,
			);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(FieldDto::fromEntity($field));
	}

	#[RouteDelete(Routes::WorkspaceField->value)]
	public function actionDeleteField(ServerRequestInterface $request, int $workspaceId, int $fieldId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}
		if (!$this->permissionChecker->canManageFields($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have permission to manage fields.');
		}

		$field = $this->fieldProvider->getField($workspace, $fieldId);
		if ($field === null) {
			return new NotFoundResponse('Field not found.');
		}

		$this->fieldProvider->deleteField($user, $field);

		return new OkResponse();
	}
}
