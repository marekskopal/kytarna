<?php

declare(strict_types=1);

namespace Kytarna\Controller;

use Kytarna\Dto\LectureFileDto;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\LectureFile;
use Kytarna\Model\Entity\User;
use Kytarna\Response\ErrorResponse;
use Kytarna\Response\NotFoundResponse;
use Kytarna\Response\OkResponse;
use Kytarna\Route\Routes;
use Kytarna\Service\Provider\LectureCodeResolverInterface;
use Kytarna\Service\Provider\LectureFileProviderInterface;
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

final readonly class LectureFileController
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
		private LectureCodeResolverInterface $lectureCodeResolver,
		private LectureFileProviderInterface $lectureFileProvider,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::LectureFiles->value)]
	public function actionGetFiles(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		$files = array_map(
			static fn (LectureFile $file): LectureFileDto => LectureFileDto::fromEntity($file),
			$this->lectureFileProvider->findByLecture($lecture),
		);

		return new JsonResponse($files);
	}

	#[RoutePost(Routes::LectureFiles->value)]
	public function actionPostFile(ServerRequestInterface $request, int|string $lectureId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
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
			$file = $this->lectureFileProvider->uploadFile($user, $lecture, $filename, $mimeType, $body);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(LectureFileDto::fromEntity($file), 201);
	}

	#[RouteGet(Routes::LectureFileContent->value)]
	public function actionGetFileContent(ServerRequestInterface $request, int|string $lectureId, int $fileId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		$file = $this->lectureFileProvider->getFile($fileId);
		if ($file === null || $file->lecture->id !== $lecture->id) {
			return new NotFoundResponse('File not found.');
		}

		try {
			$bytes = $this->lectureFileProvider->readContent($file);
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

	#[RouteDelete(Routes::LectureFile->value)]
	public function actionDeleteFile(ServerRequestInterface $request, int|string $lectureId, int $fileId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$lecture = $this->loadLectureInScope($user, $lectureId);
		if ($lecture === null) {
			return new NotFoundResponse('Lecture not found.');
		}

		$file = $this->lectureFileProvider->getFile($fileId);
		if ($file === null || $file->lecture->id !== $lecture->id) {
			return new NotFoundResponse('File not found.');
		}

		$this->lectureFileProvider->deleteFile($user, $file);

		return new OkResponse();
	}

	private function loadLectureInScope(User $user, int|string $lectureId): ?Lecture
	{
		return $this->lectureCodeResolver->resolveForUser($user, (string) $lectureId);
	}
}
