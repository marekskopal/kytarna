<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use Kytario\Model\Entity\Project;
use Kytario\Model\Entity\Task;

interface TaskFieldValueProviderInterface
{
	/** @return array<int, ?string> */
	public function findByTask(Task $task): array;

	/** @param array<int, ?string> $fieldValues */
	public function validateForProject(Project $project, array $fieldValues): void;

	/**
	 * @param array<int, ?string> $fieldValues
	 * @return list<array{fieldId: int, from: ?string, to: ?string}>
	 */
	public function persistForTask(Task $task, array $fieldValues): array;

	public function deleteAllForTask(Task $task): void;
}
