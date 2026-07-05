<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use Kytario\Model\Entity\Task;
use Kytario\Model\Entity\TaskFile;
use Kytario\Model\Entity\User;

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
