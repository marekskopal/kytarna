<?php

declare(strict_types=1);

namespace Kytarna\Controller;

use Kytarna\Dto\DateInput;
use Kytarna\Dto\PracticeSummaryDto;
use Kytarna\Dto\ProgressEntryCreateDto;
use Kytarna\Dto\ProgressEntryUpdateDto;
use Kytarna\Dto\SongProgressEntryDto;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\SongProgressEntry;
use Kytarna\Model\Entity\User;
use Kytarna\Response\ErrorResponse;
use Kytarna\Response\NotFoundResponse;
use Kytarna\Response\OkResponse;
use Kytarna\Route\Routes;
use Kytarna\Service\Provider\SongProgressProviderInterface;
use Kytarna\Service\Provider\SongProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Kytarna\Service\Request\RequestServiceInterface;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final readonly class SongProgressController
{
	public function __construct(
		private SongProviderInterface $songProvider,
		private SongProgressProviderInterface $songProgressProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::SongProgress->value)]
	public function actionGetProgress(ServerRequestInterface $request, int $songId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$song = $this->loadSongInScope($user, $songId);
		if ($song === null) {
			return new NotFoundResponse('Song not found.');
		}

		try {
			[$from, $to] = $this->parseRange($request);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 400);
		}

		$entries = array_map(
			static fn (SongProgressEntry $entry): SongProgressEntryDto => SongProgressEntryDto::fromEntity($entry),
			$this->songProgressProvider->getEntriesBySong($song, $from, $to),
		);

		return new JsonResponse($entries);
	}

	#[RoutePost(Routes::SongProgress->value)]
	public function actionPostProgress(ServerRequestInterface $request, int $songId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$song = $this->loadSongInScope($user, $songId);
		if ($song === null) {
			return new NotFoundResponse('Song not found.');
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, ProgressEntryCreateDto::class);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		$entry = $this->songProgressProvider->createEntry(
			$user,
			$song,
			$dto->practicedAt,
			$dto->note,
			$dto->tempoBpm,
			$dto->durationMinutes,
		);

		return new JsonResponse(SongProgressEntryDto::fromEntity($entry), 201);
	}

	#[RouteGet(Routes::SongPracticeSummary->value)]
	public function actionGetSummary(ServerRequestInterface $request, int $songId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$song = $this->loadSongInScope($user, $songId);
		if ($song === null) {
			return new NotFoundResponse('Song not found.');
		}

		try {
			[$from, $to] = $this->parseRange($request);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 400);
		}

		return new JsonResponse(PracticeSummaryDto::fromSummary($this->songProgressProvider->summarizeSong($song, $from, $to)));
	}

	#[RoutePut(Routes::SongProgressEntry->value)]
	public function actionPutProgress(ServerRequestInterface $request, int $progressEntryId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$entry = $this->loadEntryInScope($user, $progressEntryId);
		if ($entry === null) {
			return new NotFoundResponse('Progress entry not found.');
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, ProgressEntryUpdateDto::class);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		$entry = $this->songProgressProvider->updateEntry(
			$user,
			$entry,
			$dto->practicedAt ?? $entry->practicedAt,
			$dto->note,
			$dto->tempoBpm,
			$dto->durationMinutes,
		);

		return new JsonResponse(SongProgressEntryDto::fromEntity($entry));
	}

	#[RouteDelete(Routes::SongProgressEntry->value)]
	public function actionDeleteProgress(ServerRequestInterface $request, int $progressEntryId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$entry = $this->loadEntryInScope($user, $progressEntryId);
		if ($entry === null) {
			return new NotFoundResponse('Progress entry not found.');
		}

		$this->songProgressProvider->deleteEntry($user, $entry);

		return new OkResponse();
	}

	/** @return array{0: ?string, 1: ?string} */
	private function parseRange(ServerRequestInterface $request): array
	{
		$query = $request->getQueryParams();
		$from = isset($query['from']) && is_string($query['from']) && $query['from'] !== ''
			? DateInput::parse($query['from'], 'from')?->format('Y-m-d')
			: null;
		$to = isset($query['to']) && is_string($query['to']) && $query['to'] !== ''
			? DateInput::parse($query['to'], 'to')?->format('Y-m-d')
			: null;
		return [$from, $to];
	}

	private function loadSongInScope(User $user, int $songId): ?Song
	{
		$song = $this->songProvider->getSong($songId);
		if ($song === null || !$this->workspaceProvider->isMember($user, $song->workspace)) {
			return null;
		}
		return $song;
	}

	private function loadEntryInScope(User $user, int $entryId): ?SongProgressEntry
	{
		$entry = $this->songProgressProvider->getEntry($entryId);
		if ($entry === null || !$this->workspaceProvider->isMember($user, $entry->song->workspace)) {
			return null;
		}
		return $entry;
	}
}
