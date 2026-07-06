<?php

declare(strict_types=1);

namespace Kytarna\Controller;

use Kytarna\Dto\LectureLinkCreateDto;
use Kytarna\Dto\SongLinkDto;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\SongLink;
use Kytarna\Model\Entity\User;
use Kytarna\Response\ErrorResponse;
use Kytarna\Response\NotFoundResponse;
use Kytarna\Response\OkResponse;
use Kytarna\Route\Routes;
use Kytarna\Service\Provider\SongLinkProviderInterface;
use Kytarna\Service\Provider\SongProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Kytarna\Service\Request\RequestServiceInterface;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final readonly class SongLinkController
{
	public function __construct(
		private SongProviderInterface $songProvider,
		private SongLinkProviderInterface $songLinkProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::SongLinks->value)]
	public function actionGetLinks(ServerRequestInterface $request, int $songId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$song = $this->loadSongInScope($user, $songId);
		if ($song === null) {
			return new NotFoundResponse('Song not found.');
		}

		$links = array_map(
			static fn (SongLink $link): SongLinkDto => SongLinkDto::fromEntity($link),
			$this->songLinkProvider->getLinksBySong($song),
		);

		return new JsonResponse($links);
	}

	#[RoutePost(Routes::SongLinks->value)]
	public function actionPostLink(ServerRequestInterface $request, int $songId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$song = $this->loadSongInScope($user, $songId);
		if ($song === null) {
			return new NotFoundResponse('Song not found.');
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, LectureLinkCreateDto::class);
			$link = $this->songLinkProvider->addLink($user, $song, $dto->url, $dto->label, $dto->kind, $dto->timestampSeconds);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(SongLinkDto::fromEntity($link), 201);
	}

	#[RouteDelete(Routes::SongLink->value)]
	public function actionDeleteLink(ServerRequestInterface $request, int $songId, int $linkId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$song = $this->loadSongInScope($user, $songId);
		if ($song === null) {
			return new NotFoundResponse('Song not found.');
		}

		$link = $this->songLinkProvider->getLink($linkId);
		if ($link === null || $link->song->id !== $song->id) {
			return new NotFoundResponse('Link not found.');
		}

		$this->songLinkProvider->deleteLink($user, $link);

		return new OkResponse();
	}

	private function loadSongInScope(User $user, int $songId): ?Song
	{
		$song = $this->songProvider->getSong($songId);
		if ($song === null || !$this->workspaceProvider->isMember($user, $song->workspace)) {
			return null;
		}
		return $song;
	}
}
