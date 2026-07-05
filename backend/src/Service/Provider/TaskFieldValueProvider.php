<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use RuntimeException;
use Ukolio\Model\Entity\Enum\FieldTypeEnum;
use Ukolio\Model\Entity\Field;
use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskFieldValue;
use Ukolio\Model\Repository\TaskFieldValueRepository;
use Ukolio\Service\Semver\SemverComparator;
use const JSON_THROW_ON_ERROR;

final readonly class TaskFieldValueProvider implements TaskFieldValueProviderInterface
{
	public function __construct(
		private TaskFieldValueRepository $taskFieldValueRepository,
		private ProjectFieldProviderInterface $projectFieldProvider,
	) {
	}

	/** @return array<int, ?string> */
	public function findByTask(Task $task): array
	{
		$result = [];
		foreach ($this->taskFieldValueRepository->findByTask($task->id) as $value) {
			$result[$value->field->id] = $value->value;
		}
		return $result;
	}

	/** @param array<int, ?string> $fieldValues */
	public function validateForProject(Project $project, array $fieldValues): void
	{
		foreach ($this->projectFieldProvider->getProjectFields($project) as $pf) {
			$raw = $fieldValues[$pf->field->id] ?? null;
			$value = $raw === null ? null : trim($raw);
			if ($value === '') {
				$value = null;
			}

			if ($value === null) {
				if ($pf->field->required) {
					throw new RuntimeException('Field "' . $pf->field->name . '" is required.');
				}
				continue;
			}

			$this->validateValueAgainstField($pf->field, $value);
		}
	}

	/**
	 * @param array<int, ?string> $fieldValues
	 * @return list<array{fieldId: int, from: ?string, to: ?string}>
	 */
	public function persistForTask(Task $task, array $fieldValues): array
	{
		$attached = [];
		foreach ($this->projectFieldProvider->getProjectFields($task->project) as $pf) {
			$attached[$pf->field->id] = $pf;
		}

		$changes = [];
		$now = new DateTimeImmutable();

		foreach ($attached as $fieldId => $pf) {
			$raw = $fieldValues[$fieldId] ?? null;
			$normalized = $raw === null ? null : trim($raw);
			if ($normalized === '') {
				$normalized = null;
			}

			$existing = $this->taskFieldValueRepository->findOneByTaskAndField($task->id, $fieldId);
			$previous = $existing?->value;

			if ($normalized === null) {
				if ($existing !== null) {
					$this->taskFieldValueRepository->delete($existing);
					$changes[] = ['fieldId' => $fieldId, 'from' => $previous, 'to' => null];
				}
				continue;
			}

			if ($existing === null) {
				$value = new TaskFieldValue(task: $task, field: $pf->field, value: $normalized);
				$value->createdAt = $now;
				$value->updatedAt = $now;
				$this->taskFieldValueRepository->persist($value);
				$changes[] = ['fieldId' => $fieldId, 'from' => null, 'to' => $normalized];
			} elseif ($existing->value !== $normalized) {
				$existing->value = $normalized;
				$existing->updatedAt = $now;
				$this->taskFieldValueRepository->persist($existing);
				$changes[] = ['fieldId' => $fieldId, 'from' => $previous, 'to' => $normalized];
			}
		}

		return $changes;
	}

	public function deleteAllForTask(Task $task): void
	{
		foreach ($this->taskFieldValueRepository->findByTask($task->id) as $value) {
			$this->taskFieldValueRepository->delete($value);
		}
	}

	private function validateValueAgainstField(Field $field, string $value): void
	{
		if ($field->type === FieldTypeEnum::Select || $field->type === FieldTypeEnum::Version) {
			$options = $this->decodeOptions($field);
			if (!in_array($value, $options, true)) {
				throw new RuntimeException('Value "' . $value . '" is not allowed for field "' . $field->name . '".');
			}
		}

		if ($field->type === FieldTypeEnum::Version && !SemverComparator::isValid($value)) {
			throw new RuntimeException('Value "' . $value . '" for field "' . $field->name . '" is not a valid semver.');
		}
	}

	/** @return list<string> */
	private function decodeOptions(Field $field): array
	{
		if ($field->options === null) {
			return [];
		}
		$decoded = json_decode($field->options, true, 4, JSON_THROW_ON_ERROR);
		if (!is_array($decoded)) {
			return [];
		}
		$result = [];
		foreach ($decoded as $item) {
			if (is_string($item)) {
				$result[] = $item;
			}
		}
		return $result;
	}
}
