<?php

declare(strict_types=1);

namespace Kytario\Controller;

use Kytario\Dto\LectureWatcherDto;
use Kytario\Dto\LectureWatchersDto;
use Kytario\Model\Entity\Lecture;
use Kytario\Model\Entity\LectureWatcher;
use Kytario\Model\Entity\User;
use Kytario\Response\NotFoundResponse;
use Kytario\Route\Routes;
use Kytario\Service\Provider\LectureCodeResolverInterface;
use Kytario\Service\Provider\LectureWatcherProviderInterface;
use Kytario\Service\Request\RequestServiceInterface;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class LectureWatcherController
{
	public function __construct(
		private LectureCodeResolverInterface $lectureCodeResolver,
		private LectureWatcherProviderInterface $lectureWatcherProvider,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::LectureWatchers->value)]
	public function actionList(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		return new JsonResponse($this->watchersDto($lecture, $user));
	}

	#[RoutePost(Routes::LectureWatch->value)]
	public function actionWatch(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		$this->lectureWatcherProvider->watch($lecture, $user);

		return new JsonResponse($this->watchersDto($lecture, $user));
	}

	#[RouteDelete(Routes::LectureWatch->value)]
	public function actionUnwatch(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		$this->lectureWatcherProvider->unwatch($lecture, $user);

		return new JsonResponse($this->watchersDto($lecture, $user));
	}

	private function watchersDto(Lecture $lecture, User $user): LectureWatchersDto
	{
		$watchers = array_map(
			static fn (LectureWatcher $w): LectureWatcherDto => LectureWatcherDto::fromEntity($w),
			$this->lectureWatcherProvider->listWatchers($lecture),
		);

		return new LectureWatchersDto($watchers, $this->lectureWatcherProvider->isWatching($lecture, $user));
	}

	private function loadLectureInScope(User $user, int|string $lectureId): ?Lecture
	{
		return $this->lectureCodeResolver->resolveForUser($user, (string) $lectureId);
	}
}
