<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskWatcher;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\TaskWatcherRepository;

final readonly class TaskWatcherProvider implements TaskWatcherProviderInterface
{
	public function __construct(private TaskWatcherRepository $taskWatcherRepository)
	{
	}

	public function watch(Task $task, User $user): TaskWatcher
	{
		$existing = $this->taskWatcherRepository->findByTaskAndUser($task->id, $user->id);
		if ($existing !== null) {
			return $existing;
		}

		$now = new DateTimeImmutable();
		$watcher = new TaskWatcher(task: $task, user: $user);
		$watcher->createdAt = $now;
		$watcher->updatedAt = $now;
		$this->taskWatcherRepository->persist($watcher);

		return $watcher;
	}

	public function unwatch(Task $task, User $user): void
	{
		$existing = $this->taskWatcherRepository->findByTaskAndUser($task->id, $user->id);
		if ($existing === null) {
			return;
		}

		$this->taskWatcherRepository->delete($existing);
	}

	public function isWatching(Task $task, User $user): bool
	{
		return $this->taskWatcherRepository->findByTaskAndUser($task->id, $user->id) !== null;
	}

	/** @return list<TaskWatcher> */
	public function listWatchers(Task $task): array
	{
		$result = [];
		foreach ($this->taskWatcherRepository->findByTask($task->id) as $watcher) {
			$result[] = $watcher;
		}
		return $result;
	}

	/** @return list<int> */
	public function listWatcherUserIds(Task $task): array
	{
		$ids = [];
		foreach ($this->taskWatcherRepository->findByTask($task->id) as $watcher) {
			$ids[] = $watcher->user->id;
		}
		return $ids;
	}

	public function deleteAllForTask(Task $task): void
	{
		foreach ($this->taskWatcherRepository->findByTask($task->id) as $watcher) {
			$this->taskWatcherRepository->delete($watcher);
		}
	}
}
