<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Dto;

use Ukolio\Model\Entity\ProjectField;

final readonly class McpProjectFieldDto
{
	public function __construct(public int $fieldId, public int $position, public McpFieldDto $field,)
	{
	}

	public static function fromEntity(ProjectField $projectField): self
	{
		return new self(
			fieldId: $projectField->field->id,
			position: $projectField->position,
			field: McpFieldDto::fromEntity($projectField->field),
		);
	}
}
