<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\Field;
use const DATE_ATOM;
use const JSON_THROW_ON_ERROR;

final readonly class FieldDto
{
	/** @param list<string>|null $options */
	public function __construct(
		public int $id,
		public int $workspaceId,
		public string $name,
		public string $type,
		public bool $required,
		public ?string $defaultValue,
		public ?array $options,
		public string $createdAt,
		public string $updatedAt,
	) {
	}

	public static function fromEntity(Field $field): self
	{
		$options = null;
		if ($field->options !== null) {
			$decoded = json_decode($field->options, true, 4, JSON_THROW_ON_ERROR);
			if (is_array($decoded)) {
				$options = [];
				foreach ($decoded as $item) {
					if (is_string($item)) {
						$options[] = $item;
					}
				}
			}
		}

		return new self(
			id: $field->id,
			workspaceId: $field->workspace->id,
			name: $field->name,
			type: $field->type->value,
			required: $field->required,
			defaultValue: $field->defaultValue,
			options: $options,
			createdAt: $field->createdAt->format(DATE_ATOM),
			updatedAt: $field->updatedAt->format(DATE_ATOM),
		);
	}
}
