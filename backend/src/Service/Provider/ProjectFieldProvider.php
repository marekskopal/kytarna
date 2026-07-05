<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use RuntimeException;
use Ukolio\Model\Entity\Enum\EventTypeEnum;
use Ukolio\Model\Entity\Field;
use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\ProjectField;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\FieldRepository;
use Ukolio\Model\Repository\ProjectFieldRepository;
use Ukolio\Model\Repository\TaskFieldValueRepository;
use Ukolio\Model\Repository\TaskRepository;

final readonly class ProjectFieldProvider implements ProjectFieldProviderInterface
{
	public function __construct(
		private ProjectFieldRepository $projectFieldRepository,
		private FieldRepository $fieldRepository,
		private TaskRepository $taskRepository,
		private TaskFieldValueRepository $taskFieldValueRepository,
		private EventProviderInterface $eventProvider,
	) {
	}

	/** @return list<ProjectField> */
	public function getProjectFields(Project $project): array
	{
		return iterator_to_array($this->projectFieldRepository->findByProject($project->id), false);
	}

	/** @param list<int> $fieldIdsInOrder */
	public function setProjectFields(User $author, Project $project, array $fieldIdsInOrder): void
	{
		$this->assertUniqueIds($fieldIdsInOrder);
		$resolved = $this->resolveFields($project, $fieldIdsInOrder);
		$existing = $this->indexExistingByFieldId($project);

		$this->detachRemoved($project, $existing, $fieldIdsInOrder);
		$this->attachOrReorder($project, $existing, $resolved, $fieldIdsInOrder);

		$this->eventProvider->recordEvent(
			$author,
			$project,
			EventTypeEnum::ProjectFieldsUpdated,
			['fieldIds' => $fieldIdsInOrder],
		);
	}

	/** @param list<int> $fieldIdsInOrder */
	private function assertUniqueIds(array $fieldIdsInOrder): void
	{
		$seen = [];
		foreach ($fieldIdsInOrder as $fieldId) {
			if (isset($seen[$fieldId])) {
				throw new RuntimeException('Field id "' . $fieldId . '" was supplied more than once.');
			}
			$seen[$fieldId] = true;
		}
	}

	/**
	 * @param list<int> $fieldIdsInOrder
	 * @return array<int, Field>
	 */
	private function resolveFields(Project $project, array $fieldIdsInOrder): array
	{
		$resolved = [];
		foreach ($fieldIdsInOrder as $fieldId) {
			$field = $this->fieldRepository->findOneByWorkspaceAndId($project->workspace->id, $fieldId);
			if ($field === null) {
				throw new RuntimeException('Field id "' . $fieldId . '" does not belong to this workspace.');
			}
			$resolved[$fieldId] = $field;
		}
		return $resolved;
	}

	/** @return array<int, ProjectField> */
	private function indexExistingByFieldId(Project $project): array
	{
		$existing = [];
		foreach ($this->projectFieldRepository->findByProject($project->id) as $pf) {
			$existing[$pf->field->id] = $pf;
		}
		return $existing;
	}

	/**
	 * @param array<int, ProjectField> $existing
	 * @param list<int> $fieldIdsInOrder
	 */
	private function detachRemoved(Project $project, array $existing, array $fieldIdsInOrder): void
	{
		$keepIds = array_fill_keys($fieldIdsInOrder, true);
		foreach ($existing as $fieldId => $pf) {
			if (isset($keepIds[$fieldId])) {
				continue;
			}
			$this->removeOrphanedValues($project, $fieldId);
			$this->projectFieldRepository->delete($pf);
		}
	}

	/**
	 * @param array<int, ProjectField> $existing
	 * @param array<int, Field> $resolved
	 * @param list<int> $fieldIdsInOrder
	 */
	private function attachOrReorder(Project $project, array $existing, array $resolved, array $fieldIdsInOrder): void
	{
		$now = new DateTimeImmutable();
		$position = 0;
		foreach ($fieldIdsInOrder as $fieldId) {
			if (isset($existing[$fieldId])) {
				$this->reorderExisting($existing[$fieldId], $position, $now);
			} else {
				$this->createAttachment($project, $resolved[$fieldId], $position, $now);
			}
			$position++;
		}
	}

	private function reorderExisting(ProjectField $pf, int $position, DateTimeImmutable $now): void
	{
		if ($pf->position === $position) {
			return;
		}
		$pf->position = $position;
		$pf->updatedAt = $now;
		$this->projectFieldRepository->persist($pf);
	}

	private function createAttachment(Project $project, Field $field, int $position, DateTimeImmutable $now,): void
	{
		$pf = new ProjectField(project: $project, field: $field, position: $position);
		$pf->createdAt = $now;
		$pf->updatedAt = $now;
		$this->projectFieldRepository->persist($pf);
	}

	private function removeOrphanedValues(Project $project, int $fieldId): void
	{
		foreach ($this->taskRepository->findByProject($project->id) as $task) {
			$value = $this->taskFieldValueRepository->findOneByTaskAndField($task->id, $fieldId);
			if ($value !== null) {
				$this->taskFieldValueRepository->delete($value);
			}
		}
	}
}
