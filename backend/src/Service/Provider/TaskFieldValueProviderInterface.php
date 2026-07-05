<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\Task;

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
