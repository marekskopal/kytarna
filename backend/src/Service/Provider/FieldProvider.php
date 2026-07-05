<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use Iterator;
use RuntimeException;
use Ukolio\Model\Entity\Enum\EventTypeEnum;
use Ukolio\Model\Entity\Enum\FieldTypeEnum;
use Ukolio\Model\Entity\Field;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\FieldRepository;
use Ukolio\Model\Repository\ProjectFieldRepository;
use Ukolio\Model\Repository\TaskFieldValueRepository;
use Ukolio\Service\Semver\SemverComparator;
use const JSON_THROW_ON_ERROR;

final readonly class FieldProvider implements FieldProviderInterface
{
	public function __construct(
		private FieldRepository $fieldRepository,
		private ProjectFieldRepository $projectFieldRepository,
		private TaskFieldValueRepository $taskFieldValueRepository,
		private EventProviderInterface $eventProvider,
	) {
	}

	/** @return Iterator<Field> */
	public function getFields(Workspace $workspace): Iterator
	{
		return $this->fieldRepository->findByWorkspace($workspace->id);
	}

	public function getField(Workspace $workspace, int $fieldId): ?Field
	{
		return $this->fieldRepository->findOneByWorkspaceAndId($workspace->id, $fieldId);
	}

	/** @param array<string>|null $options */
	public function createField(
		User $author,
		Workspace $workspace,
		string $name,
		FieldTypeEnum $type,
		bool $required,
		?string $defaultValue,
		?array $options,
	): Field {
		$name = $this->validateName($workspace->id, $name, null);
		$normalizedOptions = $this->normalizeOptions($type, $options);
		$defaultValue = $this->normalizeDefault($type, $defaultValue, $normalizedOptions);

		$now = new DateTimeImmutable();
		$field = new Field(
			workspace: $workspace,
			name: $name,
			type: $type,
			required: $required,
			defaultValue: $defaultValue,
			options: $normalizedOptions !== null ? json_encode($normalizedOptions, JSON_THROW_ON_ERROR) : null,
		);
		$field->createdAt = $now;
		$field->updatedAt = $now;
		$this->fieldRepository->persist($field);

		$this->eventProvider->recordWorkspaceEvent(
			$author,
			$workspace,
			EventTypeEnum::FieldCreated,
			['fieldId' => $field->id, 'name' => $field->name, 'type' => $type->value],
		);

		return $field;
	}

	/** @param array<string>|null $options */
	public function updateField(
		User $author,
		Field $field,
		string $name,
		FieldTypeEnum $type,
		bool $required,
		?string $defaultValue,
		?array $options,
	): Field {
		$name = $this->validateName($field->workspace->id, $name, $field->id);
		$normalizedOptions = $this->normalizeOptions($type, $options);
		$defaultValue = $this->normalizeDefault($type, $defaultValue, $normalizedOptions);

		$field->name = $name;
		$field->type = $type;
		$field->required = $required;
		$field->defaultValue = $defaultValue;
		$field->options = $normalizedOptions !== null ? json_encode($normalizedOptions, JSON_THROW_ON_ERROR) : null;
		$field->updatedAt = new DateTimeImmutable();
		$this->fieldRepository->persist($field);

		$this->eventProvider->recordWorkspaceEvent(
			$author,
			$field->workspace,
			EventTypeEnum::FieldUpdated,
			['fieldId' => $field->id, 'name' => $field->name, 'type' => $type->value],
		);

		return $field;
	}

	public function deleteField(User $author, Field $field): void
	{
		$affectedProjectIds = [];
		foreach ($this->projectFieldRepository->findByField($field->id) as $projectField) {
			$affectedProjectIds[$projectField->project->id] = true;
		}

		foreach ($this->taskFieldValueRepository->findByField($field->id) as $value) {
			$this->taskFieldValueRepository->delete($value);
		}
		foreach ($this->projectFieldRepository->findByField($field->id) as $projectField) {
			$this->projectFieldRepository->delete($projectField);
		}

		$this->eventProvider->recordWorkspaceEvent(
			$author,
			$field->workspace,
			EventTypeEnum::FieldDeleted,
			['fieldId' => $field->id, 'name' => $field->name, 'affectedProjectIds' => array_keys($affectedProjectIds)],
		);

		$this->fieldRepository->delete($field);
	}

	/**
	 * @param array<string>|null $options
	 * @return array<string>|null
	 */
	public function normalizeOptions(FieldTypeEnum $type, ?array $options): ?array
	{
		if (!$type->hasOptions()) {
			return null;
		}

		if ($options === null || $options === []) {
			throw new RuntimeException('Field of type "' . $type->value . '" requires at least one option.');
		}

		$normalized = [];
		foreach ($options as $option) {
			$trimmed = trim($option);
			if ($trimmed === '') {
				continue;
			}
			if (in_array($trimmed, $normalized, true)) {
				continue;
			}
			$normalized[] = $trimmed;
		}

		if ($normalized === []) {
			throw new RuntimeException('Field of type "' . $type->value . '" requires at least one non-empty option.');
		}

		if ($type === FieldTypeEnum::Version) {
			foreach ($normalized as $option) {
				if (!SemverComparator::isValid($option)) {
					throw new RuntimeException('Version option "' . $option . '" is not a valid semver string.');
				}
			}
			$normalized = SemverComparator::sortDescending($normalized);
		}

		return $normalized;
	}

	private function validateName(int $workspaceId, string $name, ?int $excludeFieldId): string
	{
		$trimmed = trim($name);
		if ($trimmed === '') {
			throw new RuntimeException('Field name cannot be empty.');
		}

		$existing = $this->fieldRepository->findOneByWorkspaceAndName($workspaceId, $trimmed);
		if ($existing !== null && $existing->id !== $excludeFieldId) {
			throw new RuntimeException('A field with the name "' . $trimmed . '" already exists in this workspace.');
		}

		return $trimmed;
	}

	/** @param array<string>|null $normalizedOptions */
	private function normalizeDefault(FieldTypeEnum $type, ?string $defaultValue, ?array $normalizedOptions): ?string
	{
		if ($defaultValue === null) {
			return null;
		}

		$trimmed = trim($defaultValue);
		if ($trimmed === '') {
			return null;
		}

		if ($type->hasOptions()) {
			if ($normalizedOptions === null || !in_array($trimmed, $normalizedOptions, true)) {
				throw new RuntimeException('Default value "' . $trimmed . '" must be one of the field options.');
			}
		}

		return $trimmed;
	}
}
