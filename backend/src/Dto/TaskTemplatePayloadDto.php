<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use JsonException;
use RuntimeException;
use Ukolio\Model\Entity\Task;
use const JSON_THROW_ON_ERROR;

/**
 * Snapshot of a task's clonable content stored as the JSON `payload` of a TaskTemplate.
 * Comments, files, events, and relations are intentionally not part of a template.
 */
final readonly class TaskTemplatePayloadDto
{
	/**
	 * @param list<array{fieldId: int, value: ?string}> $fieldValues
	 * @param list<int> $tagIds
	 */
	public function __construct(
		public string $name,
		public ?string $description,
		public ?int $priorityId,
		public array $fieldValues,
		public array $tagIds,
	) {
	}

	/**
	 * @param array<int, ?string> $fieldValues
	 * @param list<int> $tagIds
	 */
	public static function fromTask(Task $task, array $fieldValues, array $tagIds): self
	{
		$values = [];
		foreach ($fieldValues as $fieldId => $value) {
			$values[] = ['fieldId' => $fieldId, 'value' => $value];
		}

		return new self(
			name: $task->name,
			description: $task->description,
			priorityId: $task->priority->id,
			fieldValues: $values,
			tagIds: $tagIds,
		);
	}

	public static function fromJson(string $json): self
	{
		try {
			$decoded = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new RuntimeException('Task template payload is not valid JSON.', 0, $e);
		}

		if (!is_array($decoded)) {
			throw new RuntimeException('Task template payload must be a JSON object.');
		}

		$name = $decoded['name'] ?? null;
		if (!is_string($name) || $name === '') {
			throw new RuntimeException('Task template payload is missing a task name.');
		}

		$description = $decoded['description'] ?? null;
		$priorityId = $decoded['priorityId'] ?? null;

		return new self(
			name: $name,
			description: is_string($description) ? $description : null,
			priorityId: is_int($priorityId) ? $priorityId : null,
			fieldValues: self::parseFieldValues($decoded['fieldValues'] ?? null),
			tagIds: self::parseTagIds($decoded['tagIds'] ?? null),
		);
	}

	public function toJson(): string
	{
		return json_encode(
			[
				'name' => $this->name,
				'description' => $this->description,
				'priorityId' => $this->priorityId,
				'fieldValues' => $this->fieldValues,
				'tagIds' => $this->tagIds,
			],
			JSON_THROW_ON_ERROR,
		);
	}

	/** @return array<int, ?string> */
	public function fieldValuesMap(): array
	{
		$result = [];
		foreach ($this->fieldValues as $entry) {
			$result[$entry['fieldId']] = $entry['value'];
		}
		return $result;
	}

	/** @return list<array{fieldId: int, value: ?string}> */
	private static function parseFieldValues(mixed $raw): array
	{
		if (!is_array($raw)) {
			return [];
		}
		$result = [];
		foreach ($raw as $entry) {
			if (!is_array($entry) || !isset($entry['fieldId']) || !is_int($entry['fieldId'])) {
				continue;
			}
			$value = $entry['value'] ?? null;
			$result[] = ['fieldId' => $entry['fieldId'], 'value' => is_string($value) ? $value : null];
		}
		return $result;
	}

	/** @return list<int> */
	private static function parseTagIds(mixed $raw): array
	{
		if (!is_array($raw)) {
			return [];
		}
		$result = [];
		foreach ($raw as $tagId) {
			if (is_int($tagId)) {
				$result[] = $tagId;
			}
		}
		return $result;
	}
}
