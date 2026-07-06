<?php

declare(strict_types=1);

namespace Kytarna\Controller;

use Kytarna\Dto\SongWatcherDto;
use Kytarna\Dto\SongWatchersDto;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\SongWatcher;
use Kytarna\Model\Entity\User;
use Kytarna\Response\NotFoundResponse;
use Kytarna\Route\Routes;
use Kytarna\Service\Provider\SongProviderInterface;
use Kytarna\Service\Provider\SongWatcherProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Kytarna\Service\Request\RequestServiceInterface;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class SongWatcherController
{
	public function __construct(
		private SongProviderInterface $songProvider,
		private SongWatcherProviderInterface $songWatcherProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::SongWatchers->value)]
	public function actionList(ServerRequestInterface $request, int|string $songId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$song = $this->loadSongInScope($user, $songId);
		if ($song === null) {
			return new NotFoundResponse('Song not found.');
		}

		return new JsonResponse($this->watchersDto($song, $user));
	}

	#[RoutePost(Routes::SongWatch->value)]
	public function actionWatch(ServerRequestInterface $request, int|string $songId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$song = $this->loadSongInScope($user, $songId);
		if ($song === null) {
			return new NotFoundResponse('Song not found.');
		}

		$this->songWatcherProvider->watch($song, $user);

		return new JsonResponse($this->watchersDto($song, $user));
	}

	#[RouteDelete(Routes::SongWatch->value)]
	public function actionUnwatch(ServerRequestInterface $request, int|string $songId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$song = $this->loadSongInScope($user, $songId);
		if ($song === null) {
			return new NotFoundResponse('Song not found.');
		}

		$this->songWatcherProvider->unwatch($song, $user);

		return new JsonResponse($this->watchersDto($song, $user));
	}

	private function watchersDto(Song $song, User $user): SongWatchersDto
	{
		$watchers = array_map(
			static fn (SongWatcher $w): SongWatcherDto => SongWatcherDto::fromEntity($w),
			$this->songWatcherProvider->listWatchers($song),
		);

		return new SongWatchersDto($watchers, $this->songWatcherProvider->isWatching($song, $user));
	}

	private function loadSongInScope(User $user, int|string $songId): ?Song
	{
		$song = $this->songProvider->getSong((int) $songId);
		if ($song === null || !$this->workspaceProvider->isMember($user, $song->workspace)) {
			return null;
		}
		return $song;
	}
}
