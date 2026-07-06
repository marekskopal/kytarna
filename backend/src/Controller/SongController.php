<?php

declare(strict_types=1);

namespace Kytarna\Controller;

use Kytarna\Dto\SongCourseDto;
use Kytarna\Dto\SongCreateDto;
use Kytarna\Dto\SongDto;
use Kytarna\Dto\SongListDto;
use Kytarna\Dto\SongListQueryDto;
use Kytarna\Dto\SongMoveDto;
use Kytarna\Dto\SongUpdateDto;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Response\ErrorResponse;
use Kytarna\Response\NotAuthorizedResponse;
use Kytarna\Response\NotFoundResponse;
use Kytarna\Response\OkResponse;
use Kytarna\Route\Routes;
use Kytarna\Service\Auth\PermissionCheckerInterface;
use Kytarna\Service\Provider\CourseProviderInterface;
use Kytarna\Service\Provider\ProgressStatusProviderInterface;
use Kytarna\Service\Provider\SongProviderInterface;
use Kytarna\Service\Provider\SongTagProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Kytarna\Service\Request\RequestServiceInterface;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Stream;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use const UPLOAD_ERR_OK;

final readonly class SongController
{
	private const array SafeCoverMimeTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/avif'];

	public function __construct(
		private SongProviderInterface $songProvider,
		private CourseProviderInterface $courseProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private SongTagProviderInterface $songTagProvider,
		private ProgressStatusProviderInterface $progressStatusProvider,
		private PermissionCheckerInterface $permissionChecker,
		private RequestServiceInterface $requestService,
	) {
	}

	/** Song response with the viewing user's personal board status applied. */
	private function songResponse(User $user, Song $song): SongDto
	{
		return SongDto::fromEntity(
			$song,
			$this->songTagProvider->getTagIdsForSong($song),
			$this->progressStatusProvider->statusForSong($user, $song),
		);
	}

	#[RouteGet(Routes::Songs->value)]
	public function actionGetSongs(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->currentWorkspace($request);
		if ($workspace === null) {
			return new ErrorResponse('No active workspace.', 422);
		}

		try {
			$q = SongListQueryDto::fromQueryParams($request->getQueryParams());
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 400);
		}

		$songs = iterator_to_array(
			$this->songProvider->getSongsInWorkspace(
				$workspace,
				$q->limit,
				$q->offset,
				$q->orderBy,
				$q->direction,
				$q->search,
				$q->statuses,
				$q->onlyActive,
				$q->archived,
			),
			false,
		);
		$count = $this->songProvider->countSongsInWorkspace($workspace, $q->search, $q->statuses, $q->onlyActive, $q->archived);

		$tagsBySongId = $this->songTagProvider->getTagIdsBySongIds(array_map(static fn (Song $s): int => $s->id, $songs));
		$statusBySongId = $this->progressStatusProvider->songStatusesForUserInWorkspace($user, $workspace);

		return new JsonResponse(new SongListDto(
			songs: array_map(
				static fn (Song $s): SongDto => SongDto::fromEntity($s, $tagsBySongId[$s->id] ?? [], $statusBySongId[$s->id] ?? null),
				$songs,
			),
			count: $count,
		));
	}

	#[RoutePost(Routes::Songs->value)]
	public function actionPostSong(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->currentWorkspace($request);
		if ($workspace === null) {
			return new ErrorResponse('No active workspace.', 422);
		}

		if (!$this->permissionChecker->canManageSongs($user, $workspace)) {
			return new NotAuthorizedResponse('Only the teacher can create songs.');
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, SongCreateDto::class);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		$course = null;
		if ($dto->courseId !== null) {
			$course = $this->courseProvider->getCourse($workspace, $dto->courseId);
			if ($course === null) {
				return new NotFoundResponse('Course not found in this workspace.');
			}
		}

		try {
			$song = $this->songProvider->createSong(
				author: $user,
				workspace: $workspace,
				name: $dto->name,
				status: $dto->status,
				description: $dto->description,
				tuning: $dto->tuning,
				capo: $dto->capo,
				targetTempoBpm: $dto->targetTempoBpm,
				difficulty: $dto->difficulty,
				authorName: $dto->authorName,
				albumName: $dto->albumName,
				course: $course,
				tagIds: $dto->tagIds,
			);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse($this->songResponse($user, $song), 201);
	}

	#[RouteGet(Routes::Song->value)]
	public function actionGetSong(ServerRequestInterface $request, int $songId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$song = $this->loadSong($request, $songId);
		if ($song === null) {
			return new NotFoundResponse('Song not found.');
		}
		return new JsonResponse($this->songResponse($user, $song));
	}

	#[RoutePut(Routes::Song->value)]
	public function actionPutSong(ServerRequestInterface $request, int $songId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$song = $this->loadSong($request, $songId);
		if ($song === null) {
			return new NotFoundResponse('Song not found.');
		}

		if (!$this->permissionChecker->canManageSongs($user, $song->workspace)) {
			return new NotAuthorizedResponse('Only the teacher can edit songs.');
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, SongUpdateDto::class);
			$song = $this->songProvider->updateSong(
				author: $user,
				song: $song,
				name: $dto->name,
				description: $dto->description,
				status: $dto->status,
				tuning: $dto->tuning,
				capo: $dto->capo,
				targetTempoBpm: $dto->targetTempoBpm,
				difficulty: $dto->difficulty,
				authorName: $dto->authorName,
				albumName: $dto->albumName,
				tagIds: $dto->tagIds,
			);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse($this->songResponse($user, $song));
	}

	#[RoutePut(Routes::SongMove->value)]
	public function actionPutSongMove(ServerRequestInterface $request, int $songId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$song = $this->loadSong($request, $songId);
		if ($song === null) {
			return new NotFoundResponse('Song not found.');
		}

		// A card drag records the viewing user's personal progress, not the shared template.
		if (!$this->permissionChecker->canTrackProgress($user, $song->workspace)) {
			return new NotAuthorizedResponse('You cannot track progress in this workspace.');
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, SongMoveDto::class);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		$this->progressStatusProvider->setSongStatus($user, $song, $dto->status);

		return new JsonResponse($this->songResponse($user, $song));
	}

	#[RoutePost(Routes::SongArchive->value)]
	public function actionPostSongArchive(ServerRequestInterface $request, int $songId): ResponseInterface
	{
		$song = $this->requireManageableSong($request, $songId);
		if ($song instanceof ResponseInterface) {
			return $song;
		}
		$user = $this->requestService->getUser($request);
		return new JsonResponse($this->songResponse($user, $this->songProvider->archiveSong($user, $song)));
	}

	#[RoutePost(Routes::SongUnarchive->value)]
	public function actionPostSongUnarchive(ServerRequestInterface $request, int $songId): ResponseInterface
	{
		$song = $this->requireManageableSong($request, $songId);
		if ($song instanceof ResponseInterface) {
			return $song;
		}
		$user = $this->requestService->getUser($request);
		return new JsonResponse($this->songResponse($user, $this->songProvider->unarchiveSong($user, $song)));
	}

	#[RoutePut(Routes::SongCourse->value)]
	public function actionPutSongCourse(ServerRequestInterface $request, int $songId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->currentWorkspace($request);
		if ($workspace === null) {
			return new ErrorResponse('No active workspace.', 422);
		}
		$song = $this->loadSongInWorkspace($workspace, $songId);
		if ($song === null) {
			return new NotFoundResponse('Song not found.');
		}

		if (!$this->permissionChecker->canManageSongs($user, $workspace)) {
			return new NotAuthorizedResponse('Only the teacher can attach songs to courses.');
		}

		try {
			$dto = $this->requestService->getRequestBodyDto($request, SongCourseDto::class);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		if ($dto->courseId === null) {
			$song = $this->songProvider->removeFromCourse($user, $song);
			return new JsonResponse($this->songResponse($user, $song));
		}

		$course = $this->courseProvider->getCourse($workspace, $dto->courseId);
		if ($course === null) {
			return new NotFoundResponse('Course not found in this workspace.');
		}

		try {
			$song = $this->songProvider->addToCourse($user, $song, $course);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse($this->songResponse($user, $song));
	}

	#[RoutePost(Routes::SongCover->value)]
	public function actionPostSongCover(ServerRequestInterface $request, int $songId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$song = $this->loadSong($request, $songId);
		if ($song === null) {
			return new NotFoundResponse('Song not found.');
		}

		if (!$this->permissionChecker->canManageSongs($user, $song->workspace)) {
			return new NotAuthorizedResponse('Only the teacher can set a song cover.');
		}

		$uploaded = $request->getUploadedFiles()['file'] ?? null;
		if (!$uploaded instanceof UploadedFileInterface) {
			return new ErrorResponse('Missing "file" multipart field.', 422);
		}
		if ($uploaded->getError() !== UPLOAD_ERR_OK) {
			return new ErrorResponse('Upload failed with code ' . $uploaded->getError() . '.', 422);
		}

		$filename = $uploaded->getClientFilename() ?? 'cover';
		$mimeType = $uploaded->getClientMediaType() ?? 'application/octet-stream';
		$body = $uploaded->getStream()->getContents();

		try {
			$song = $this->songProvider->setCover($user, $song, $filename, $mimeType, $body);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse($this->songResponse($user, $song));
	}

	#[RouteGet(Routes::SongCover->value)]
	public function actionGetSongCover(ServerRequestInterface $request, int $songId): ResponseInterface
	{
		$song = $this->loadSong($request, $songId);
		if ($song?->coverImageKey === null) {
			return new NotFoundResponse('Cover not found.');
		}

		try {
			$bytes = $this->songProvider->readCover($song);
		} catch (RuntimeException $e) {
			return new ErrorResponse('Failed to read cover: ' . $e->getMessage(), 500);
		}

		$stream = new Stream('php://temp', 'wb+');
		$stream->write($bytes);
		$stream->rewind();

		$mime = $song->coverImageMimeType ?? 'application/octet-stream';
		return new Response($stream, 200, [
			'Content-Type' => in_array(strtolower($mime), self::SafeCoverMimeTypes, true) ? $mime : 'application/octet-stream',
			'Content-Length' => (string) strlen($bytes),
			'Cache-Control' => 'private, max-age=300',
			'X-Content-Type-Options' => 'nosniff',
		]);
	}

	#[RouteDelete(Routes::SongCover->value)]
	public function actionDeleteSongCover(ServerRequestInterface $request, int $songId): ResponseInterface
	{
		$song = $this->requireManageableSong($request, $songId);
		if ($song instanceof ResponseInterface) {
			return $song;
		}
		$user = $this->requestService->getUser($request);
		return new JsonResponse($this->songResponse($user, $this->songProvider->deleteCover($user, $song)));
	}

	#[RouteDelete(Routes::Song->value)]
	public function actionDeleteSong(ServerRequestInterface $request, int $songId): ResponseInterface
	{
		$song = $this->requireManageableSong($request, $songId);
		if ($song instanceof ResponseInterface) {
			return $song;
		}
		$user = $this->requestService->getUser($request);
		$this->songProvider->deleteSong($user, $song);
		return new OkResponse();
	}

	private function currentWorkspace(ServerRequestInterface $request): ?Workspace
	{
		return $this->workspaceProvider->getCurrentWorkspace($this->requestService->getUser($request));
	}

	/** Loads a song the current user is allowed to manage (Teacher only), or the error response to send. */
	private function requireManageableSong(ServerRequestInterface $request, int $songId): Song|ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$song = $this->loadSong($request, $songId);
		if ($song === null) {
			return new NotFoundResponse('Song not found.');
		}
		if (!$this->permissionChecker->canManageSongs($user, $song->workspace)) {
			return new NotAuthorizedResponse('Only the teacher can manage songs.');
		}
		return $song;
	}

	private function loadSong(ServerRequestInterface $request, int $songId): ?Song
	{
		$workspace = $this->currentWorkspace($request);
		if ($workspace === null) {
			return null;
		}
		return $this->loadSongInWorkspace($workspace, $songId);
	}

	private function loadSongInWorkspace(Workspace $workspace, int $songId): ?Song
	{
		$song = $this->songProvider->getSong($songId);
		if ($song === null || $song->workspace->id !== $workspace->id) {
			return null;
		}
		return $song;
	}
}
