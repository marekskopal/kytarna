<?php

declare(strict_types=1);

namespace Kytario\Mcp\Dto;

use Kytario\Dto\TaskTemplatePayloadDto;
use Kytario\Model\Entity\TaskTemplate;

final readonly class McpTaskTemplateDto
{
	/**
	 * @param list<McpTaskFieldValueDto> $fieldValues
	 * @param list<int> $tagIds
	 */
	public function __construct(
		public int $id,
		public int $workspaceId,
		public string $name,
		public string $taskName,
		public ?string $description,
		public ?int $priorityId,
		public array $fieldValues,
		public array $tagIds,
	) {
	}

	public static function fromEntity(TaskTemplate $template): self
	{
		$payload = TaskTemplatePayloadDto::fromJson($template->payload);

		$values = [];
		foreach ($payload->fieldValues as $entry) {
			$values[] = new McpTaskFieldValueDto(fieldId: $entry['fieldId'], value: $entry['value']);
		}

		return new self(
			id: $template->id,
			workspaceId: $template->workspace->id,
			name: $template->name,
			taskName: $payload->name,
			description: $payload->description,
			priorityId: $payload->priorityId,
			fieldValues: $values,
			tagIds: $payload->tagIds,
		);
	}
}
