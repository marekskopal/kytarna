<?php

declare(strict_types=1);

namespace Kytario\Model\Entity;

use Kytario\Model\Repository\TaskWatcherRepository;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;

/**
 * A user subscribed to a task's activity (U-83, Trello-style). Watchers are added automatically when
 * a user is assigned, comments, or is mentioned, and can be toggled manually. They receive
 * comment / move / due-date notifications for the task.
 */
#[Entity(repositoryClass: TaskWatcherRepository::class)]
class TaskWatcher extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Task::class)]
		public readonly Task $task,
		#[ManyToOne(entityClass: User::class)]
		public readonly User $user,
	) {
	}
}
