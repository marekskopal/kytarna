<?php

declare(strict_types=1);

namespace Kytarna\Controller;

use Kytarna\Dto\SongTabDto;
use Kytarna\Dto\TabCreateDto;
use Kytarna\Dto\TabUpdateDto;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\SongTab;
use Kytarna\Model\Entity\User;
use Kytarna\Response\ErrorResponse;
use Kytarna\Response\NotFoundResponse;
use Kytarna\Response\OkResponse;
use Kytarna\Route\Routes;
use Kytarna\Service\Provider\SongProviderInterface;
use Kytarna\Service\Provider\SongTabProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Kytarna\Service\Request\RequestServiceInterface;
use Kytarna\Service\Tab\Dto\TabValidationError;
use Kytarna\Service\Tab\Exception\TabServiceException;
use Kytarna\Service\Tab\Exception\TabValidationException;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use const UPLOAD_ERR_OK;

final readonly class SongTabController
{
	public function __construct(
		private SongProviderInterface $songProvider,
		private SongTabProviderInterface $songTabProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::SongTabs->value)]
	public function actionGetTabs(ServerRequestInterface $request, int $songId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$song = $this->loadSongInScope($user, $songId);
		if ($song === null) {
			return new NotFoundResponse('Song not found.');
		}

		$tabs = array_map(
			static fn (SongTab $tab): SongTabDto => SongTabDto::fromEntity($tab),
			$this->songTabProvider->getTabsBySong($song),
		);

		return new JsonResponse($tabs);
	}

	#[RoutePost(Routes::SongTabs->value)]
	public function actionPostTab(ServerRequestInterface $request, int $songId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$song = $this->loadSongInScope($user, $songId);
		if ($song === null) {
			return new NotFoundResponse('Song not found.');
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, TabCreateDto::class);
			$tab = $this->songTabProvider->createTab($user, $song, $dto->name, $dto->alphaTex);
		} catch (TabValidationException $e) {
			return $this->validationErrorResponse($e);
		} catch (TabServiceException $e) {
			return new ErrorResponse('Tab service unavailable: ' . $e->getMessage(), 502);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(SongTabDto::fromEntity($tab), 201);
	}

	#[RoutePost(Routes::SongTabsImport->value)]
	public function actionImportTab(ServerRequestInterface $request, int $songId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$song = $this->loadSongInScope($user, $songId);
		if ($song === null) {
			return new NotFoundResponse('Song not found.');
		}

		$uploaded = $request->getUploadedFiles()['file'] ?? null;
		if (!$uploaded instanceof UploadedFileInterface) {
			return new ErrorResponse('Missing "file" multipart field.', 422);
		}
		if ($uploaded->getError() !== UPLOAD_ERR_OK) {
			return new ErrorResponse('Upload failed with code ' . $uploaded->getError() . '.', 422);
		}

		$filename = $uploaded->getClientFilename() ?? 'import.gp';
		$parsedBody = $request->getParsedBody();
		$name = is_array($parsedBody) && isset($parsedBody['name']) && is_string($parsedBody['name']) && $parsedBody['name'] !== ''
			? $parsedBody['name']
			: $filename;
		$bytes = $uploaded->getStream()->getContents();

		try {
			$tab = $this->songTabProvider->importGpFile($user, $song, $name, $filename, $bytes);
		} catch (TabValidationException $e) {
			return $this->validationErrorResponse($e);
		} catch (TabServiceException $e) {
			return new ErrorResponse('Tab service unavailable: ' . $e->getMessage(), 502);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(SongTabDto::fromEntity($tab), 201);
	}

	#[RouteGet(Routes::SongTab->value)]
	public function actionGetTab(ServerRequestInterface $request, int $tabId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$tab = $this->loadTabInScope($user, $tabId);
		if ($tab === null) {
			return new NotFoundResponse('Tab not found.');
		}

		return new JsonResponse(SongTabDto::fromEntity($tab));
	}

	#[RoutePut(Routes::SongTab->value)]
	public function actionPutTab(ServerRequestInterface $request, int $tabId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$tab = $this->loadTabInScope($user, $tabId);
		if ($tab === null) {
			return new NotFoundResponse('Tab not found.');
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, TabUpdateDto::class);
			$tab = $this->songTabProvider->updateTab($user, $tab, $dto->name, $dto->alphaTex);
		} catch (TabValidationException $e) {
			return $this->validationErrorResponse($e);
		} catch (TabServiceException $e) {
			return new ErrorResponse('Tab service unavailable: ' . $e->getMessage(), 502);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(SongTabDto::fromEntity($tab));
	}

	#[RouteDelete(Routes::SongTab->value)]
	public function actionDeleteTab(ServerRequestInterface $request, int $tabId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$tab = $this->loadTabInScope($user, $tabId);
		if ($tab === null) {
			return new NotFoundResponse('Tab not found.');
		}

		$this->songTabProvider->deleteTab($user, $tab);

		return new OkResponse();
	}

	private function validationErrorResponse(TabValidationException $e): JsonResponse
	{
		return new JsonResponse([
			'message' => 'alphaTex validation failed.',
			'errors' => array_map(
				static fn (TabValidationError $error): array => $error->toArray(),
				$e->getErrors(),
			),
		], 422);
	}

	private function loadSongInScope(User $user, int $songId): ?Song
	{
		$song = $this->songProvider->getSong($songId);
		if ($song === null || !$this->workspaceProvider->isMember($user, $song->workspace)) {
			return null;
		}
		return $song;
	}

	private function loadTabInScope(User $user, int $tabId): ?SongTab
	{
		$tab = $this->songTabProvider->getTab($tabId);
		if ($tab === null || !$this->workspaceProvider->isMember($user, $tab->song->workspace)) {
			return null;
		}
		return $tab;
	}
}
