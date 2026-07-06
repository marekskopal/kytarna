<?php

declare(strict_types=1);

namespace Kytarna\Controller;

use Kytarna\Dto\DateInput;
use Kytarna\Dto\PracticeSummaryDto;
use Kytarna\Dto\ProgressEntryCreateDto;
use Kytarna\Dto\ProgressEntryDto;
use Kytarna\Dto\ProgressEntryUpdateDto;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\ProgressEntry;
use Kytarna\Model\Entity\User;
use Kytarna\Response\ErrorResponse;
use Kytarna\Response\NotFoundResponse;
use Kytarna\Response\OkResponse;
use Kytarna\Route\Routes;
use Kytarna\Service\Provider\CourseProviderInterface;
use Kytarna\Service\Provider\LectureCodeResolverInterface;
use Kytarna\Service\Provider\ProgressProviderInterface;
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

final readonly class ProgressController
{
	public function __construct(
		private LectureCodeResolverInterface $lectureCodeResolver,
		private CourseProviderInterface $courseProvider,
		private ProgressProviderInterface $progressProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::LectureProgress->value)]
	public function actionGetProgress(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		try {
			[$from, $to] = $this->parseRange($request);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 400);
		}

		$entries = array_map(
			static fn (ProgressEntry $entry): ProgressEntryDto => ProgressEntryDto::fromEntity($entry),
			$this->progressProvider->getEntriesByLecture($user, $lecture, $from, $to),
		);

		return new JsonResponse($entries);
	}

	#[RoutePost(Routes::LectureProgress->value)]
	public function actionPostProgress(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, ProgressEntryCreateDto::class);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		$entry = $this->progressProvider->createEntry(
			$user,
			$lecture,
			$dto->practicedAt,
			$dto->note,
			$dto->tempoBpm,
			$dto->durationMinutes,
		);

		return new JsonResponse(ProgressEntryDto::fromEntity($entry), 201);
	}

	#[RouteGet(Routes::LecturePracticeSummary->value)]
	public function actionGetLectureSummary(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		try {
			[$from, $to] = $this->parseRange($request);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 400);
		}

		return new JsonResponse(PracticeSummaryDto::fromSummary($this->progressProvider->summarizeLecture($user, $lecture, $from, $to)));
	}

	#[RouteGet(Routes::CoursePracticeSummary->value)]
	public function actionGetCourseSummary(ServerRequestInterface $request, int $courseId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);
		if ($workspace === null) {
			return new NotFoundResponse('Course not found.');
		}
		$course = $this->courseProvider->getCourse($workspace, $courseId);
		if ($course === null) {
			return new NotFoundResponse('Course not found.');
		}

		try {
			[$from, $to] = $this->parseRange($request);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 400);
		}

		return new JsonResponse(PracticeSummaryDto::fromSummary($this->progressProvider->summarizeCourse($user, $course, $from, $to)));
	}

	#[RoutePut(Routes::ProgressEntry->value)]
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

		$entry = $this->progressProvider->updateEntry(
			$user,
			$entry,
			$dto->practicedAt ?? $entry->practicedAt,
			$dto->note,
			$dto->tempoBpm,
			$dto->durationMinutes,
		);

		return new JsonResponse(ProgressEntryDto::fromEntity($entry));
	}

	#[RouteDelete(Routes::ProgressEntry->value)]
	public function actionDeleteProgress(ServerRequestInterface $request, int $progressEntryId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$entry = $this->loadEntryInScope($user, $progressEntryId);
		if ($entry === null) {
			return new NotFoundResponse('Progress entry not found.');
		}

		$this->progressProvider->deleteEntry($user, $entry);

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

	private function loadLectureInScope(User $user, int|string $lectureId): ?Lecture
	{
		return $this->lectureCodeResolver->resolveForUser($user, (string) $lectureId);
	}

	private function loadEntryInScope(User $user, int $entryId): ?ProgressEntry
	{
		$entry = $this->progressProvider->getEntry($entryId);
		// A practice entry is personal: only its author may read/update/delete it.
		if ($entry === null || $entry->user->id !== $user->id) {
			return null;
		}
		return $entry;
	}
}
