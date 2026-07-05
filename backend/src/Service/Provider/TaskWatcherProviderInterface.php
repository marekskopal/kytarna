<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use Kytario\Model\Entity\Task;
use Kytario\Model\Entity\TaskWatcher;
use Kytario\Model\Entity\User;

interface TaskWatcherProviderInterface
{
	/** Idempotent — returns the existing watch row if the user already watches the task. */
	public function watch(Task $task, User $user): TaskWatcher;

	public function unwatch(Task $task, User $user): void;

	public function isWatching(Task $task, User $user): bool;

	/** @return list<TaskWatcher> */
	public function listWatchers(Task $task): array;

	/** @return list<int> */
	public function listWatcherUserIds(Task $task): array;

	public function deleteAllForTask(Task $task): void;
}
