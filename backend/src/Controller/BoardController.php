<?php

declare(strict_types=1);

namespace Kytarna\Controller;

use Kytarna\Dto\BoardDto;
use Kytarna\Dto\CourseDto;
use Kytarna\Dto\LectureDto;
use Kytarna\Dto\SongDto;
use Kytarna\Model\Entity\Enum\LearningStatusEnum;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\Song;
use Kytarna\Response\NotFoundResponse;
use Kytarna\Route\Routes;
use Kytarna\Service\Provider\CourseProviderInterface;
use Kytarna\Service\Provider\LectureProviderInterface;
use Kytarna\Service\Provider\LectureTagProviderInterface;
use Kytarna\Service\Provider\ProgressStatusProviderInterface;
use Kytarna\Service\Provider\SongProviderInterface;
use Kytarna\Service\Provider\SongTagProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Kytarna\Service\Request\RequestServiceInterface;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteGet;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class BoardController
{
	public function __construct(
		private CourseProviderInterface $courseProvider,
		private LectureProviderInterface $lectureProvider,
		private LectureTagProviderInterface $lectureTagProvider,
		private SongProviderInterface $songProvider,
		private SongTagProviderInterface $songTagProvider,
		private ProgressStatusProviderInterface $progressStatusProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::CourseBoard->value)]
	public function actionGetBoard(ServerRequestInterface $request, int $courseId): ResponseInterface
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

		$statuses = array_map(static fn (LearningStatusEnum $s): string => $s->value, LearningStatusEnum::cases());

		// The board is personal: each viewer sees their own ToLearn/Learning/Mastered column per item.
		$lectureStatuses = $this->progressStatusProvider->lectureStatusesForUserInCourse($user, $course);
		$songStatuses = $this->progressStatusProvider->songStatusesForUserInCourse($user, $course);

		$courseLectures = iterator_to_array($this->lectureProvider->getLecturesByCourse($course, includeArchived: false), false);
		$lectureIds = array_map(static fn (Lecture $t): int => $t->id, $courseLectures);
		$tagsByLectureId = $this->lectureTagProvider->getTagIdsByLectureIds($lectureIds);
		$lectures = array_map(
			static fn (Lecture $t): LectureDto => LectureDto::fromEntity(
				$t,
				$tagsByLectureId[$t->id] ?? [],
				$lectureStatuses[$t->id] ?? null,
			),
			$courseLectures,
		);

		$courseSongs = iterator_to_array($this->songProvider->getSongsByCourse($course, includeArchived: false), false);
		$tagsBySongId = $this->songTagProvider->getTagIdsBySongIds(array_map(static fn (Song $s): int => $s->id, $courseSongs));
		$songs = array_map(
			static fn (Song $s): SongDto => SongDto::fromEntity($s, $tagsBySongId[$s->id] ?? [], $songStatuses[$s->id] ?? null),
			$courseSongs,
		);

		return new JsonResponse(new BoardDto(
			course: CourseDto::fromEntity($course),
			statuses: $statuses,
			lectures: $lectures,
			songs: $songs,
		));
	}
}
