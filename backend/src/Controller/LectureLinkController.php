<?php

declare(strict_types=1);

namespace Kytario\Controller;

use Kytario\Dto\LectureLinkCreateDto;
use Kytario\Dto\LectureLinkDto;
use Kytario\Model\Entity\Lecture;
use Kytario\Model\Entity\LectureLink;
use Kytario\Model\Entity\User;
use Kytario\Response\ErrorResponse;
use Kytario\Response\NotFoundResponse;
use Kytario\Response\OkResponse;
use Kytario\Route\Routes;
use Kytario\Service\Provider\LectureCodeResolverInterface;
use Kytario\Service\Provider\LinkProviderInterface;
use Kytario\Service\Request\RequestServiceInterface;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final readonly class LectureLinkController
{
	public function __construct(
		private LectureCodeResolverInterface $lectureCodeResolver,
		private LinkProviderInterface $linkProvider,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::LectureLinks->value)]
	public function actionGetLinks(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		$links = array_map(
			static fn (LectureLink $link): LectureLinkDto => LectureLinkDto::fromEntity($link),
			$this->linkProvider->getLinksByLecture($lecture),
		);

		return new JsonResponse($links);
	}

	#[RoutePost(Routes::LectureLinks->value)]
	public function actionPostLink(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, LectureLinkCreateDto::class);
			$link = $this->linkProvider->addLink($user, $lecture, $dto->url, $dto->label, $dto->kind, $dto->timestampSeconds);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(LectureLinkDto::fromEntity($link), 201);
	}

	#[RouteDelete(Routes::LectureLink->value)]
	public function actionDeleteLink(ServerRequestInterface $request, int|string $lectureId, int $linkId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		$link = $this->linkProvider->getLink($linkId);
		if ($link === null || $link->lecture->id !== $lecture->id) {
			return new NotFoundResponse('Link not found.');
		}

		$this->linkProvider->deleteLink($user, $link);

		return new OkResponse();
	}

	private function loadLectureInScope(User $user, int|string $lectureId): ?Lecture
	{
		return $this->lectureCodeResolver->resolveForUser($user, (string) $lectureId);
	}
}
