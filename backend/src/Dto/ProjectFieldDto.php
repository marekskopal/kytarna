<?php

declare(strict_types=1);

namespace Kytario\Dto;

use Kytario\Model\Entity\ProjectField;

final readonly class ProjectFieldDto
{
	public function __construct(public int $fieldId, public int $position, public FieldDto $field,)
	{
	}

	public static function fromEntity(ProjectField $projectField): self
	{
		return new self(
			fieldId: $projectField->field->id,
			position: $projectField->position,
			field: FieldDto::fromEntity($projectField->field),
		);
	}
}
