<?php

declare(strict_types=1);

namespace Kytario\Controller;

use Kytario\Dto\TagCreateDto;
use Kytario\Dto\TagDto;
use Kytario\Dto\TagUpdateDto;
use Kytario\Model\Entity\Tag;
use Kytario\Response\ErrorResponse;
use Kytario\Response\NotAuthorizedResponse;
use Kytario\Response\NotFoundResponse;
use Kytario\Response\OkResponse;
use Kytario\Route\Routes;
use Kytario\Service\Auth\PermissionCheckerInterface;
use Kytario\Service\Provider\TagProviderInterface;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use Kytario\Service\Request\RequestServiceInterface;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

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
