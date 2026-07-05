<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use DateTimeImmutable;
use RuntimeException;
use Kytario\Model\Entity\Enum\EventTypeEnum;
use Kytario\Model\Entity\Task;
use Kytario\Model\Entity\TaskFile;
use Kytario\Model\Entity\User;
use Kytario\Model\Repository\TaskFileRepository;
use Kytario\Service\Actor\ActorContextInterface;
use Kytario\Service\Storage\FileStorageInterface;
use Kytario\Service\Storage\S3Config;

final readonly class TaskFileProvider implements TaskFileProviderInterface
{
	public function __construct(
		private TaskFileRepository $taskFileRepository,
		private FileStorageInterface $fileStorage,
		private S3Config $s3Config,
		private EventProviderInterface $eventProvider,
		private ActorContextInterface $actorContext,
	) {
	}

	public function getMaxFileSizeBytes(): int
	{
		return $this->s3Config->maxFileSizeBytes;
	}

	/** @return list<TaskFile> */
	public function findByTask(Task $task): array
	{
		$result = [];
		foreach ($this->taskFileRepository->findByTask($task->id) as $file) {
			$result[] = $file;
		}
		return $result;
	}

	public function getFile(int $fileId): ?TaskFile
	{
		return $this->taskFileRepository->findOneById($fileId);
	}

	public function uploadFile(User $author, Task $task, string $filename, string $mimeType, string $body): TaskFile
	{
		$size = strlen($body);
		if ($size === 0) {
			throw new RuntimeException('File body is empty.');
		}
		$max = $this->s3Config->maxFileSizeBytes;
		if ($size > $max) {
			throw new RuntimeException(sprintf(
				'File is %d bytes, exceeds the %d-byte limit.',
				$size,
				$max,
			));
		}

		$cleanFilename = $this->sanitizeFilename($filename);
		$cleanMimeType = $this->sanitizeMimeType($mimeType);
		$storageKey = $this->buildStorageKey($task, $cleanFilename);

		$now = new DateTimeImmutable();
		$file = new TaskFile(
			task: $task,
			filename: $cleanFilename,
			mimeType: $cleanMimeType,
			size: $size,
			storageKey: $storageKey,
			uploadedBy: $author,
			uploadedByAgent: $this->actorContext->isAgent(),
		);
		$file->createdAt = $now;
		$file->updatedAt = $now;

		$this->taskFileRepository->persist($file);

		try {
			$this->fileStorage->put($storageKey, $body, $cleanMimeType);
		} catch (\Throwable $e) {
			$this->taskFileRepository->delete($file);

			throw new RuntimeException('Failed to store file: ' . $e->getMessage(), 0, $e);
		}

		$this->eventProvider->recordEvent(
			$author,
			$task->project,
			EventTypeEnum::TaskFileAdded,
			['fileId' => $file->id, 'filename' => $cleanFilename, 'size' => $size],
			$task->id,
		);

		return $file;
	}

	public function readContent(TaskFile $file): string
	{
		return $this->fileStorage->get($file->storageKey);
	}

	public function deleteFile(User $author, TaskFile $file): void
	{
		$this->fileStorage->delete($file->storageKey);
		$this->taskFileRepository->delete($file);

		$this->eventProvider->recordEvent(
			$author,
			$file->task->project,
			EventTypeEnum::TaskFileDeleted,
			['fileId' => $file->id, 'filename' => $file->filename, 'size' => $file->size],
			$file->task->id,
		);
	}

	public function deleteAllForTask(User $author, Task $task): void
	{
		foreach ($this->taskFileRepository->findByTask($task->id) as $file) {
			$this->fileStorage->delete($file->storageKey);
			$this->taskFileRepository->delete($file);
		}
	}

	private function buildStorageKey(Task $task, string $filename): string
	{
		$uuid = bin2hex(random_bytes(16));
		return sprintf('workspaces/%d/tasks/%d/%s-%s', $task->project->workspace->id, $task->id, $uuid, $filename);
	}

	private function sanitizeFilename(string $filename): string
	{
		$basename = basename(str_replace(['\\', '/'], '_', $filename));
		$basename = preg_replace('/[^A-Za-z0-9._\-]+/', '_', $basename) ?? '';
		$basename = trim($basename, '._-');
		if ($basename === '') {
			$basename = 'file';
		}
		if (strlen($basename) > 200) {
			$basename = substr($basename, 0, 200);
		}
		return $basename;
	}

	private function sanitizeMimeType(string $mimeType): string
	{
		$trimmed = trim($mimeType);
		if ($trimmed === '') {
			return 'application/octet-stream';
		}
		if (preg_match('~^[a-zA-Z0-9!#$&\^_.+-]+/[a-zA-Z0-9!#$&\^_.+-]+$~', $trimmed) !== 1) {
			return 'application/octet-stream';
		}
		if (strlen($trimmed) > 191) {
			return 'application/octet-stream';
		}
		return $trimmed;
	}
}
