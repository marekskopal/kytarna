<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use Kytario\Model\Entity\Enum\TaskRelationTypeEnum;
use Kytario\Model\Entity\Task;
use Kytario\Model\Entity\TaskRelation;
use Kytario\Model\Entity\User;

interface TaskRelationProviderInterface
{
	/** @return list<TaskRelation> */
	public function findOutgoing(Task $task): array;

	/** @return list<TaskRelation> */
	public function findIncoming(Task $task): array;

	public function getRelation(int $relationId): ?TaskRelation;

	public function createRelation(User $author, Task $source, Task $target, TaskRelationTypeEnum $type): TaskRelation;

	public function deleteRelation(User $author, TaskRelation $relation): void;

	public function deleteAllForTask(Task $task): void;
}
