<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskFile;
use Ukolio\Model\Entity\User;

interface TaskFileProviderInterface
{
	public function getMaxFileSizeBytes(): int;

	/** @return list<TaskFile> */
	public function findByTask(Task $task): array;

	public function getFile(int $fileId): ?TaskFile;

	public function uploadFile(User $author, Task $task, string $filename, string $mimeType, string $body,): TaskFile;

	public function readContent(TaskFile $file): string;

	public function deleteFile(User $author, TaskFile $file): void;

	public function deleteAllForTask(User $author, Task $task): void;
}
