<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use DateTimeImmutable;
use Kytario\Model\Entity\Priority;
use Kytario\Model\Entity\Project;
use Kytario\Model\Entity\Task;
use Kytario\Model\Entity\TaskRelation;
use Kytario\Model\Entity\User;

interface SubtaskProviderInterface
{
	/** @return list<TaskRelation> */
	public function getSubtaskRelations(Task $parent): array;

	/**
	 * @param list<int> $taskIds
	 * @return array<int, array{total: int, done: int}>
	 */
	public function getSubtaskCounts(array $taskIds): array;

	public function createSubtask(
		User $author,
		Task $parent,
		string $name,
		?string $description = null,
		?Priority $priority = null,
		?DateTimeImmutable $dueDate = null,
		?User $assignee = null,
	): TaskRelation;

	/** @return array{startStatusId: ?int, finishStatusId: ?int} */
	public function getToggleStatusIds(Project $project): array;
}
