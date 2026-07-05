<?php

declare(strict_types=1);

namespace Kytario\Controller;

use Kytario\Dto\TaskFileDto;
use Kytario\Model\Entity\Task;
use Kytario\Model\Entity\TaskFile;
use Kytario\Model\Entity\User;
use Kytario\Response\ErrorResponse;
use Kytario\Response\NotFoundResponse;
use Kytario\Response\OkResponse;
use Kytario\Route\Routes;
use Kytario\Service\Provider\TaskCodeResolverInterface;
use Kytario\Service\Provider\TaskFileProviderInterface;
use Kytario\Service\Request\RequestServiceInterface;
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

final readonly class TaskFileController
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
		private TaskCodeResolverInterface $taskCodeResolver,
		private TaskFileProviderInterface $taskFileProvider,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::TaskFiles->value)]
	public function actionGetFiles(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->loadTaskInScope($user, $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}

		$files = array_map(
			static fn (TaskFile $file): TaskFileDto => TaskFileDto::fromEntity($file),
			$this->taskFileProvider->findByTask($task),
		);

		return new JsonResponse($files);
	}

	#[RoutePost(Routes::TaskFiles->value)]
	public function actionPostFile(ServerRequestInterface $request, int|string $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->loadTaskInScope($user, $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
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
			$file = $this->taskFileProvider->uploadFile($user, $task, $filename, $mimeType, $body);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(TaskFileDto::fromEntity($file), 201);
	}

	#[RouteGet(Routes::TaskFileContent->value)]
	public function actionGetFileContent(ServerRequestInterface $request, int|string $taskId, int $fileId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->loadTaskInScope($user, $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}

		$file = $this->taskFileProvider->getFile($fileId);
		if ($file === null || $file->task->id !== $task->id) {
			return new NotFoundResponse('File not found.');
		}

		try {
			$bytes = $this->taskFileProvider->readContent($file);
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

	#[RouteDelete(Routes::TaskFile->value)]
	public function actionDeleteFile(ServerRequestInterface $request, int|string $taskId, int $fileId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->loadTaskInScope($user, $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}

		$file = $this->taskFileProvider->getFile($fileId);
		if ($file === null || $file->task->id !== $task->id) {
			return new NotFoundResponse('File not found.');
		}

		$this->taskFileProvider->deleteFile($user, $file);

		return new OkResponse();
	}

	private function loadTaskInScope(User $user, int|string $taskId): ?Task
	{
		return $this->taskCodeResolver->resolveForUser($user, (string) $taskId);
	}
}
