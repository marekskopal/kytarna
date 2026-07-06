<?php

declare(strict_types=1);

namespace Kytarna\Controller;

use Kytarna\Dto\LectureCreateDto;
use Kytarna\Dto\LectureDto;
use Kytarna\Dto\LectureListDto;
use Kytarna\Dto\LectureListItemDto;
use Kytarna\Dto\LectureListQueryDto;
use Kytarna\Dto\LectureMoveDto;
use Kytarna\Dto\LectureUpdateDto;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\User;
use Kytarna\Response\ErrorResponse;
use Kytarna\Response\NotFoundResponse;
use Kytarna\Response\OkResponse;
use Kytarna\Route\Routes;
use Kytarna\Service\Provider\CourseProviderInterface;
use Kytarna\Service\Provider\LectureCodeResolverInterface;
use Kytarna\Service\Provider\LectureProviderInterface;
use Kytarna\Service\Provider\LectureTagProviderInterface;
use Kytarna\Service\Provider\StatusProviderInterface;
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

final readonly class LectureController
{
	public function __construct(
		private CourseProviderInterface $courseProvider,
		private LectureProviderInterface $lectureProvider,
		private LectureCodeResolverInterface $lectureCodeResolver,
		private StatusProviderInterface $statusProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private LectureTagProviderInterface $lectureTagProvider,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::Lectures->value)]
	public function actionGetWorkspaceLectures(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);
		if ($workspace === null) {
			return new ErrorResponse('No active workspace.', 422);
		}

		try {
			$listQuery = LectureListQueryDto::fromQueryParams($request->getQueryParams());
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 400);
		}

		$lectures = iterator_to_array(
			$this->lectureProvider->getLecturesInWorkspace(
				$workspace,
				$listQuery->limit,
				$listQuery->offset,
				$listQuery->orderBy,
				$listQuery->direction,
				$listQuery->search,
				$listQuery->statusIds,
				$listQuery->onlyActive,
				$listQuery->tagIds,
				$listQuery->archived,
			),
			false,
		);

		$count = $this->lectureProvider->countLecturesInWorkspace(
			$workspace,
			$listQuery->search,
			$listQuery->statusIds,
			$listQuery->onlyActive,
			$listQuery->tagIds,
			$listQuery->archived,
		);

		$lectureIds = array_map(static fn (Lecture $t): int => $t->id, $lectures);
		$tagsByLectureId = $this->lectureTagProvider->getTagIdsByLectureIds($lectureIds);

		return new JsonResponse(new LectureListDto(
			lectures: array_map(
				static fn (Lecture $t): LectureListItemDto => LectureListItemDto::fromEntity(
					$t,
					$tagsByLectureId[$t->id] ?? [],
				),
				$lectures,
			),
			count: $count,
		));
	}

	#[RouteGet(Routes::CourseLectures->value)]
	public function actionGetLectures(ServerRequestInterface $request, int $courseId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);
		if ($workspace === null) {
			return new NotFoundResponse('Course with id "' . $courseId . '" was not found.');
		}

		$course = $this->courseProvider->getCourse($workspace, $courseId);
		if ($course === null) {
			return new NotFoundResponse('Course with id "' . $courseId . '" was not found.');
		}

		$courseLectures = iterator_to_array($this->lectureProvider->getLecturesByCourse($course, includeArchived: false), false);
		$tagsByLectureId = $this->lectureTagProvider->getTagIdsByLectureIds(
			array_map(static fn (Lecture $t): int => $t->id, $courseLectures),
		);

		$lectures = array_map(
			static fn (Lecture $t): LectureDto => LectureDto::fromEntity($t, $tagsByLectureId[$t->id] ?? []),
			$courseLectures,
		);

		return new JsonResponse($lectures);
	}

	#[RoutePost(Routes::CourseLectures->value)]
	public function actionPostLecture(ServerRequestInterface $request, int $courseId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);
		if ($workspace === null) {
			return new NotFoundResponse('Course with id "' . $courseId . '" was not found.');
		}

		$course = $this->courseProvider->getCourse($workspace, $courseId);
		if ($course === null) {
			return new NotFoundResponse('Course with id "' . $courseId . '" was not found.');
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, LectureCreateDto::class);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		$status = $this->statusProvider->getStatus($dto->statusId);
		if ($status === null || $status->workflow->course->id !== $course->id) {
			return new NotFoundResponse('Status not found in this course.');
		}

		try {
			$lecture = $this->lectureProvider->createLecture(
				author: $user,
				course: $course,
				status: $status,
				name: $dto->name,
				description: $dto->description,
				tagIds: $dto->tagIds,
				tuning: $dto->tuning,
				capo: $dto->capo,
				targetTempoBpm: $dto->targetTempoBpm,
				difficulty: $dto->difficulty,
			);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(LectureDto::fromEntity($lecture, $this->lectureTagProvider->getTagIdsForLecture($lecture)));
	}

	#[RouteGet(Routes::Lecture->value)]
	public function actionGetLecture(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		return new JsonResponse(LectureDto::fromEntity($lecture, $this->lectureTagProvider->getTagIdsForLecture($lecture)));
	}

	#[RoutePut(Routes::Lecture->value)]
	public function actionPutLecture(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, LectureUpdateDto::class);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		$status = $this->statusProvider->getStatus($dto->statusId);
		if ($status === null || $status->workflow->course->id !== $lecture->course->id) {
			return new NotFoundResponse('Status not found in this course.');
		}

		try {
			$lecture = $this->lectureProvider->updateLecture(
				author: $user,
				lecture: $lecture,
				name: $dto->name,
				description: $dto->description,
				status: $status,
				tagIds: $dto->tagIds,
				tuning: $dto->tuning,
				capo: $dto->capo,
				targetTempoBpm: $dto->targetTempoBpm,
				difficulty: $dto->difficulty,
			);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(LectureDto::fromEntity($lecture, $this->lectureTagProvider->getTagIdsForLecture($lecture)));
	}

	#[RoutePut(Routes::LectureMove->value)]
	public function actionPutLectureMove(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, LectureMoveDto::class);

		$newStatus = $this->statusProvider->getStatus($dto->statusId);
		if ($newStatus === null || $newStatus->workflow->course->id !== $lecture->course->id) {
			return new NotFoundResponse('Status not found in this course.');
		}

		$lecture = $this->lectureProvider->moveLecture($user, $lecture, $newStatus, $dto->position);

		return new JsonResponse(LectureDto::fromEntity($lecture, $this->lectureTagProvider->getTagIdsForLecture($lecture)));
	}

	#[RoutePost(Routes::LectureArchive->value)]
	public function actionPostLectureArchive(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		$lecture = $this->lectureProvider->archiveLecture($user, $lecture);

		return new JsonResponse(LectureDto::fromEntity($lecture, $this->lectureTagProvider->getTagIdsForLecture($lecture)));
	}

	#[RoutePost(Routes::LectureUnarchive->value)]
	public function actionPostLectureUnarchive(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		$lecture = $this->lectureProvider->unarchiveLecture($user, $lecture);

		return new JsonResponse(LectureDto::fromEntity($lecture, $this->lectureTagProvider->getTagIdsForLecture($lecture)));
	}

	#[RouteDelete(Routes::Lecture->value)]
	public function actionDeleteLecture(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		$this->lectureProvider->deleteLecture($user, $lecture);

		return new OkResponse();
	}

	private function loadLectureInScope(User $user, int|string $lectureId): ?Lecture
	{
		return $this->lectureCodeResolver->resolveForUser($user, (string) $lectureId);
	}
}
