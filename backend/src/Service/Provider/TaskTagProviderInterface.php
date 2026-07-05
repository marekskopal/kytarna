<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\Workspace;

interface TaskTagProviderInterface
{
	/** @return list<int> */
	public function getTagIdsForTask(Task $task): array;

	/**
	 * @param list<int> $taskIds
	 * @return array<int, list<int>> task id => list of tag ids
	 */
	public function getTagIdsByTaskIds(array $taskIds): array;

	/**
	 * Replace the set of tags applied to a task with the given list.
	 *
	 * @param list<int> $tagIds
	 * @return array{added: list<int>, removed: list<int>}
	 */
	public function setTagsForTask(Workspace $workspace, Task $task, array $tagIds): array;

	public function deleteAllForTask(Task $task): void;
}
