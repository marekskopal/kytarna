<?php

declare(strict_types=1);

namespace Ukolio\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Ukolio\Dto\TagCreateDto;
use Ukolio\Dto\TagDto;
use Ukolio\Dto\TagUpdateDto;
use Ukolio\Model\Entity\Tag;
use Ukolio\Response\ErrorResponse;
use Ukolio\Response\NotAuthorizedResponse;
use Ukolio\Response\NotFoundResponse;
use Ukolio\Response\OkResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Auth\PermissionCheckerInterface;
use Ukolio\Service\Provider\TagProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Request\RequestServiceInterface;

final readonly class TagController
{
	public function __construct(
		private TagProviderInterface $tagProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::WorkspaceTags->value)]
	public function actionGetTags(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}
		if (!$this->permissionChecker->canViewWorkspace($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have access to this workspace.');
		}

		$tags = array_map(
			fn (Tag $tag): TagDto => TagDto::fromEntity($tag),
			iterator_to_array($this->tagProvider->getTags($workspace), false),
		);

		return new JsonResponse($tags);
	}

	#[RoutePost(Routes::WorkspaceTags->value)]
	public function actionPostTag(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}
		if (!$this->permissionChecker->canManageTags($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have permission to manage tags.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, TagCreateDto::class);

		try {
			$tag = $this->tagProvider->createTag(author: $user, workspace: $workspace, name: $dto->name, color: $dto->color);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(TagDto::fromEntity($tag));
	}

	#[RoutePut(Routes::WorkspaceTag->value)]
	public function actionPutTag(ServerRequestInterface $request, int $workspaceId, int $tagId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}
		if (!$this->permissionChecker->canManageTags($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have permission to manage tags.');
		}

		$tag = $this->tagProvider->getTag($workspace, $tagId);
		if ($tag === null) {
			return new NotFoundResponse('Tag not found.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, TagUpdateDto::class);

		try {
			$tag = $this->tagProvider->updateTag(author: $user, tag: $tag, name: $dto->name, color: $dto->color);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(TagDto::fromEntity($tag));
	}

	#[RouteDelete(Routes::WorkspaceTag->value)]
	public function actionDeleteTag(ServerRequestInterface $request, int $workspaceId, int $tagId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}
		if (!$this->permissionChecker->canManageTags($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have permission to manage tags.');
		}

		$tag = $this->tagProvider->getTag($workspace, $tagId);
		if ($tag === null) {
			return new NotFoundResponse('Tag not found.');
		}

		$this->tagProvider->deleteTag($user, $tag);

		return new OkResponse();
	}
}
