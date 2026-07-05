<?php

declare(strict_types=1);

namespace Kytario\Controller;

use Kytario\Dto\BoardDto;
use Kytario\Dto\CourseDto;
use Kytario\Dto\LectureDto;
use Kytario\Dto\StatusDto;
use Kytario\Dto\WorkflowDto;
use Kytario\Model\Entity\Lecture;
use Kytario\Model\Entity\Status;
use Kytario\Response\NotFoundResponse;
use Kytario\Route\Routes;
use Kytario\Service\Provider\CourseProviderInterface;
use Kytario\Service\Provider\LectureProviderInterface;
use Kytario\Service\Provider\LectureTagProviderInterface;
use Kytario\Service\Provider\StatusProviderInterface;
use Kytario\Service\Provider\WorkflowProviderInterface;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use Kytario\Service\Request\RequestServiceInterface;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteGet;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class BoardController
{
	public function __construct(
		private CourseProviderInterface $courseProvider,
		private WorkflowProviderInterface $workflowProvider,
		private StatusProviderInterface $statusProvider,
		private LectureProviderInterface $lectureProvider,
		private LectureTagProviderInterface $lectureTagProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::CourseBoard->value)]
	public function actionGetBoard(ServerRequestInterface $request, int $courseId): ResponseInterface
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->requestService->getUser($request));
		if ($workspace === null) {
			return new NotFoundResponse('Course with id "' . $courseId . '" was not found.');
		}

		$course = $this->courseProvider->getCourse($workspace, $courseId);
		if ($course === null) {
			return new NotFoundResponse('Course with id "' . $courseId . '" was not found.');
		}

		$workflow = $this->workflowProvider->getWorkflowByCourse($course);
		if ($workflow === null) {
			return new NotFoundResponse('Course has no workflow.');
		}

		$statuses = array_map(
			fn (Status $s): StatusDto => StatusDto::fromEntity($s),
			iterator_to_array($this->statusProvider->getStatuses($workflow), false),
		);

		$courseLectures = iterator_to_array($this->lectureProvider->getLecturesByCourse($course, includeArchived: false), false);
		$lectureIds = array_map(static fn (Lecture $t): int => $t->id, $courseLectures);
		$tagsByLectureId = $this->lectureTagProvider->getTagIdsByLectureIds($lectureIds);
		$lectures = array_map(
			static fn (Lecture $t): LectureDto => LectureDto::fromEntity($t, $tagsByLectureId[$t->id] ?? []),
			$courseLectures,
		);

		return new JsonResponse(new BoardDto(
			course: CourseDto::fromEntity($course),
			workflow: WorkflowDto::fromEntity($workflow),
			statuses: $statuses,
			lectures: $lectures,
		));
	}
}
