<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskRecurrence;
use Ukolio\Model\Entity\User;
use Ukolio\Service\Recurrence\RecurrenceConfig;

interface TaskRecurrenceProviderInterface
{
	public function findByTask(Task $task): ?TaskRecurrence;

	public function findById(int $id): ?TaskRecurrence;

	/** Create or replace the recurrence rule on a task. Throws RuntimeException on invalid config. */
	public function set(User $author, Task $task, RecurrenceConfig $config): TaskRecurrence;

	public function clear(User $author, Task $task): void;

	/** Create the next occurrence and advance the series; null when the end condition is reached. */
	public function spawnNext(TaskRecurrence $recurrence): ?Task;
}
