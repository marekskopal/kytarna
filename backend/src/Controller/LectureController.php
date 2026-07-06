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
use Kytarna\Response\NotAuthorizedResponse;
use Kytarna\Response\NotFoundResponse;
use Kytarna\Response\OkResponse;
use Kytarna\Route\Routes;
use Kytarna\Service\Auth\PermissionCheckerInterface;
use Kytarna\Service\Provider\CourseProviderInterface;
use Kytarna\Service\Provider\LectureCodeResolverInterface;
use Kytarna\Service\Provider\LectureProviderInterface;
use Kytarna\Service\Provider\LectureTagProviderInterface;
use Kytarna\Service\Provider\ProgressStatusProviderInterface;
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
		private WorkspaceProviderInterface $workspaceProvider,
		private LectureTagProviderInterface $lectureTagProvider,
		private ProgressStatusProviderInterface $progressStatusProvider,
		private PermissionCheckerInterface $permissionChecker,
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
				$listQuery->statuses,
				$listQuery->onlyActive,
				$listQuery->tagIds,
				$listQuery->archived,
			),
			false,
		);

		$count = $this->lectureProvider->countLecturesInWorkspace(
			$workspace,
			$listQuery->search,
			$listQuery->statuses,
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
		$statusByLectureId = $this->progressStatusProvider->lectureStatusesForUserInCourse($user, $course);

		$lectures = array_map(
			static fn (Lecture $t): LectureDto => LectureDto::fromEntity(
				$t,
				$tagsByLectureId[$t->id] ?? [],
				$statusByLectureId[$t->id] ?? null,
			),
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

		if (!$this->permissionChecker->canManageLectures($user, $workspace)) {
			return new NotAuthorizedResponse('Only the teacher can create lectures.');
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, LectureCreateDto::class);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		try {
			$lecture = $this->lectureProvider->createLecture(
				author: $user,
				course: $course,
				status: $dto->status,
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

		return new JsonResponse($this->lectureResponse($user, $lecture));
	}

	#[RouteGet(Routes::Lecture->value)]
	public function actionGetLecture(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		return new JsonResponse($this->lectureResponse($user, $lecture));
	}

	#[RoutePut(Routes::Lecture->value)]
	public function actionPutLecture(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		if (!$this->permissionChecker->canManageLectures($user, $lecture->course->workspace)) {
			return new NotAuthorizedResponse('Only the teacher can edit lectures.');
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, LectureUpdateDto::class);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		try {
			$lecture = $this->lectureProvider->updateLecture(
				author: $user,
				lecture: $lecture,
				name: $dto->name,
				description: $dto->description,
				status: $dto->status,
				tagIds: $dto->tagIds,
				tuning: $dto->tuning,
				capo: $dto->capo,
				targetTempoBpm: $dto->targetTempoBpm,
				difficulty: $dto->difficulty,
			);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse($this->lectureResponse($user, $lecture));
	}

	#[RoutePut(Routes::LectureMove->value)]
	public function actionPutLectureMove(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		// A card drag records the viewing user's personal progress, not the shared template.
		// Any member (Teacher or Student) may track their own progress.
		if (!$this->permissionChecker->canTrackProgress($user, $lecture->course->workspace)) {
			return new NotAuthorizedResponse('You cannot track progress in this workspace.');
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, LectureMoveDto::class);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		$this->progressStatusProvider->setLectureStatus($user, $lecture, $dto->status);

		return new JsonResponse($this->lectureResponse($user, $lecture));
	}

	#[RoutePost(Routes::LectureArchive->value)]
	public function actionPostLectureArchive(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		if (!$this->permissionChecker->canManageLectures($user, $lecture->course->workspace)) {
			return new NotAuthorizedResponse('Only the teacher can archive lectures.');
		}

		$lecture = $this->lectureProvider->archiveLecture($user, $lecture);

		return new JsonResponse($this->lectureResponse($user, $lecture));
	}

	#[RoutePost(Routes::LectureUnarchive->value)]
	public function actionPostLectureUnarchive(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		if (!$this->permissionChecker->canManageLectures($user, $lecture->course->workspace)) {
			return new NotAuthorizedResponse('Only the teacher can unarchive lectures.');
		}

		$lecture = $this->lectureProvider->unarchiveLecture($user, $lecture);

		return new JsonResponse($this->lectureResponse($user, $lecture));
	}

	#[RouteDelete(Routes::Lecture->value)]
	public function actionDeleteLecture(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		if (!$this->permissionChecker->canManageLectures($user, $lecture->course->workspace)) {
			return new NotAuthorizedResponse('Only the teacher can delete lectures.');
		}

		$this->lectureProvider->deleteLecture($user, $lecture);

		return new OkResponse();
	}

	private function loadLectureInScope(User $user, int|string $lectureId): ?Lecture
	{
		return $this->lectureCodeResolver->resolveForUser($user, (string) $lectureId);
	}

	/** Build a lecture response with the viewing user's personal board status applied. */
	private function lectureResponse(User $user, Lecture $lecture): LectureDto
	{
		return LectureDto::fromEntity(
			$lecture,
			$this->lectureTagProvider->getTagIdsForLecture($lecture),
			$this->progressStatusProvider->statusForLecture($user, $lecture),
		);
	}
}
