<?php

declare(strict_types=1);

namespace Kytarna\Controller;

use Kytarna\Dto\TabCreateDto;
use Kytarna\Dto\TabDto;
use Kytarna\Dto\TabUpdateDto;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\Tab;
use Kytarna\Model\Entity\User;
use Kytarna\Response\ErrorResponse;
use Kytarna\Response\NotFoundResponse;
use Kytarna\Response\OkResponse;
use Kytarna\Route\Routes;
use Kytarna\Service\Provider\LectureCodeResolverInterface;
use Kytarna\Service\Provider\TabProviderInterface;
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

final readonly class TabController
{
	public function __construct(
		private LectureCodeResolverInterface $lectureCodeResolver,
		private TabProviderInterface $tabProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::LectureTabs->value)]
	public function actionGetTabs(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		$tabs = array_map(
			static fn (Tab $tab): TabDto => TabDto::fromEntity($tab),
			$this->tabProvider->getTabsByLecture($lecture),
		);

		return new JsonResponse($tabs);
	}

	#[RoutePost(Routes::LectureTabs->value)]
	public function actionPostTab(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, TabCreateDto::class);
			$tab = $this->tabProvider->createTab($user, $lecture, $dto->name, $dto->alphaTex);
		} catch (TabValidationException $e) {
			return $this->validationErrorResponse($e);
		} catch (TabServiceException $e) {
			return new ErrorResponse('Tab service unavailable: ' . $e->getMessage(), 502);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(TabDto::fromEntity($tab), 201);
	}

	#[RoutePost(Routes::LectureTabsImport->value)]
	public function actionImportTab(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
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
			$tab = $this->tabProvider->importGpFile($user, $lecture, $name, $filename, $bytes);
		} catch (TabValidationException $e) {
			return $this->validationErrorResponse($e);
		} catch (TabServiceException $e) {
			return new ErrorResponse('Tab service unavailable: ' . $e->getMessage(), 502);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(TabDto::fromEntity($tab), 201);
	}

	#[RouteGet(Routes::Tab->value)]
	public function actionGetTab(ServerRequestInterface $request, int $tabId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$tab = $this->loadTabInScope($user, $tabId);
		if ($tab === null) {
			return new NotFoundResponse('Tab not found.');
		}

		return new JsonResponse(TabDto::fromEntity($tab));
	}

	#[RoutePut(Routes::Tab->value)]
	public function actionPutTab(ServerRequestInterface $request, int $tabId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$tab = $this->loadTabInScope($user, $tabId);
		if ($tab === null) {
			return new NotFoundResponse('Tab not found.');
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, TabUpdateDto::class);
			$tab = $this->tabProvider->updateTab($user, $tab, $dto->name, $dto->alphaTex);
		} catch (TabValidationException $e) {
			return $this->validationErrorResponse($e);
		} catch (TabServiceException $e) {
			return new ErrorResponse('Tab service unavailable: ' . $e->getMessage(), 502);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(TabDto::fromEntity($tab));
	}

	#[RouteDelete(Routes::Tab->value)]
	public function actionDeleteTab(ServerRequestInterface $request, int $tabId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$tab = $this->loadTabInScope($user, $tabId);
		if ($tab === null) {
			return new NotFoundResponse('Tab not found.');
		}

		$this->tabProvider->deleteTab($user, $tab);

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

	private function loadLectureInScope(User $user, int|string $lectureId): ?Lecture
	{
		return $this->lectureCodeResolver->resolveForUser($user, (string) $lectureId);
	}

	private function loadTabInScope(User $user, int $tabId): ?Tab
	{
		$tab = $this->tabProvider->getTab($tabId);
		if ($tab === null || !$this->workspaceProvider->isMember($user, $tab->lecture->course->workspace)) {
			return null;
		}
		return $tab;
	}
}
