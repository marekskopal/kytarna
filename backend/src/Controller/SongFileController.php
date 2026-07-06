<?php

declare(strict_types=1);

namespace Kytarna\Controller;

use Kytarna\Dto\SongFileDto;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\SongFile;
use Kytarna\Model\Entity\User;
use Kytarna\Response\ErrorResponse;
use Kytarna\Response\NotFoundResponse;
use Kytarna\Response\OkResponse;
use Kytarna\Route\Routes;
use Kytarna\Service\Provider\SongFileProviderInterface;
use Kytarna\Service\Provider\SongProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Kytarna\Service\Request\RequestServiceInterface;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Stream;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use const UPLOAD_ERR_OK;

final readonly class SongFileController
{
	/**
	 * The stored MIME type is client-supplied at upload time. Only these types are
	 * echoed back on download; anything else is forced to application/octet-stream
	 * so a mislabelled payload can't be rendered by the browser. Deliberately
	 * excludes scriptable types like text/html and image/svg+xml.
	 */
	private const array SafeDownloadMimeTypes = [
		'image/png',
		'image/jpeg',
		'image/gif',
		'image/webp',
		'application/pdf',
		'application/zip',
		'application/json',
		'text/plain',
		'text/csv',
		'video/mp4',
		'audio/mpeg',
	];

	public function __construct(
		private SongProviderInterface $songProvider,
		private SongFileProviderInterface $songFileProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::SongFiles->value)]
	public function actionGetFiles(ServerRequestInterface $request, int $songId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$song = $this->loadSongInScope($user, $songId);
		if ($song === null) {
			return new NotFoundResponse('Song not found.');
		}

		$files = array_map(
			static fn (SongFile $file): SongFileDto => SongFileDto::fromEntity($file),
			$this->songFileProvider->findBySong($song),
		);

		return new JsonResponse($files);
	}

	#[RoutePost(Routes::SongFiles->value)]
	public function actionPostFile(ServerRequestInterface $request, int $songId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$song = $this->loadSongInScope($user, $songId);
		if ($song === null) {
			return new NotFoundResponse('Song not found.');
		}

		$uploaded = $request->getUploadedFiles()['file'] ?? null;
		if (!$uploaded instanceof UploadedFileInterface) {
			return new ErrorResponse('Missing "file" multipart field.', 422);
		}
		if ($uploaded->getError() !== UPLOAD_ERR_OK) {
			return new ErrorResponse('Upload failed with code ' . $uploaded->getError() . '.', 422);
		}

		$filename = $uploaded->getClientFilename() ?? 'file';
		$mimeType = $uploaded->getClientMediaType() ?? 'application/octet-stream';
		$body = $uploaded->getStream()->getContents();

		try {
			$file = $this->songFileProvider->uploadFile($user, $song, $filename, $mimeType, $body);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(SongFileDto::fromEntity($file), 201);
	}

	#[RouteGet(Routes::SongFileContent->value)]
	public function actionGetFileContent(ServerRequestInterface $request, int $songId, int $fileId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$song = $this->loadSongInScope($user, $songId);
		if ($song === null) {
			return new NotFoundResponse('Song not found.');
		}

		$file = $this->songFileProvider->getFile($fileId);
		if ($file === null || $file->song->id !== $song->id) {
			return new NotFoundResponse('File not found.');
		}

		try {
			$bytes = $this->songFileProvider->readContent($file);
		} catch (RuntimeException $e) {
			return new ErrorResponse('Failed to read file: ' . $e->getMessage(), 500);
		}

		$stream = new Stream('php://temp', 'wb+');
		$stream->write($bytes);
		$stream->rewind();

		return new Response($stream, 200, [
			'Content-Type' => self::downloadContentType($file->mimeType),
			'Content-Length' => (string) $file->size,
			'Content-Disposition' => 'attachment; filename="' . addslashes($file->filename) . '"',
			'X-Content-Type-Options' => 'nosniff',
		]);
	}

	private static function downloadContentType(string $mimeType): string
	{
		return in_array(strtolower($mimeType), self::SafeDownloadMimeTypes, true) ? $mimeType : 'application/octet-stream';
	}

	#[RouteDelete(Routes::SongFile->value)]
	public function actionDeleteFile(ServerRequestInterface $request, int $songId, int $fileId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$song = $this->loadSongInScope($user, $songId);
		if ($song === null) {
			return new NotFoundResponse('Song not found.');
		}

		$file = $this->songFileProvider->getFile($fileId);
		if ($file === null || $file->song->id !== $song->id) {
			return new NotFoundResponse('File not found.');
		}

		$this->songFileProvider->deleteFile($user, $file);

		return new OkResponse();
	}

	private function loadSongInScope(User $user, int $songId): ?Song
	{
		$song = $this->songProvider->getSong($songId);
		if ($song === null || !$this->workspaceProvider->isMember($user, $song->workspace)) {
			return null;
		}
		return $song;
	}
}
