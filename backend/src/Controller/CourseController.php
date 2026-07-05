<?php

declare(strict_types=1);

namespace Kytario\Controller;

use Kytario\Dto\CourseCreateDto;
use Kytario\Dto\CourseDto;
use Kytario\Dto\CourseUpdateDto;
use Kytario\Model\Entity\Course;
use Kytario\Response\ErrorResponse;
use Kytario\Response\NotAuthorizedResponse;
use Kytario\Response\NotFoundResponse;
use Kytario\Response\OkResponse;
use Kytario\Route\Routes;
use Kytario\Service\Auth\PermissionCheckerInterface;
use Kytario\Service\Provider\CourseProviderInterface;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use Kytario\Service\Request\RequestServiceInterface;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final readonly class CourseController
{
	public function __construct(
		private CourseProviderInterface $courseProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::Courses->value)]
	public function actionGetCourses(ServerRequestInterface $request): ResponseInterface
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->requestService->getUser($request));
		if ($workspace === null) {
			return new JsonResponse([]);
		}

		$courses = array_map(
			fn (Course $p): CourseDto => CourseDto::fromEntity($p),
			iterator_to_array($this->courseProvider->getCourses($workspace), false),
		);

		return new JsonResponse($courses);
	}

	#[RouteGet(Routes::Course->value)]
	public function actionGetCourse(ServerRequestInterface $request, int $courseId): ResponseInterface
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->requestService->getUser($request));
		if ($workspace === null) {
			return new NotFoundResponse('Course with id "' . $courseId . '" was not found.');
		}

		$course = $this->courseProvider->getCourse($workspace, $courseId);
		if ($course === null) {
			return new NotFoundResponse('Course with id "' . $courseId . '" was not found.');
		}

		return new JsonResponse(CourseDto::fromEntity($course));
	}

	#[RoutePost(Routes::Courses->value)]
	public function actionPostCourse(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);
		if ($workspace === null) {
			return new ErrorResponse('No active workspace.', 422);
		}

		if (!$this->permissionChecker->canManageCourses($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have permission to create courses.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, CourseCreateDto::class);

		try {
			$course = $this->courseProvider->createCourse($user, $workspace, $dto->name, $dto->description);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(CourseDto::fromEntity($course));
	}

	#[RoutePut(Routes::Course->value)]
	public function actionPutCourse(ServerRequestInterface $request, int $courseId): ResponseInterface
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

		if (!$this->permissionChecker->canManageCourses($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have permission to update courses.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, CourseUpdateDto::class);

		try {
			$course = $this->courseProvider->updateCourse(
				author: $user,
				course: $course,
				name: $dto->name ?? $course->name,
				description: $dto->description ?? $course->description,
			);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(CourseDto::fromEntity($course));
	}

	#[RouteDelete(Routes::Course->value)]
	public function actionDeleteCourse(ServerRequestInterface $request, int $courseId): ResponseInterface
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

		if (!$this->permissionChecker->canManageCourses($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have permission to delete courses.');
		}

		$this->courseProvider->deleteCourse($course);

		return new OkResponse();
	}
}
