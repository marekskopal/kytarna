<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Iterator;
use Ukolio\Model\Entity\Enum\FieldTypeEnum;
use Ukolio\Model\Entity\Field;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;

interface FieldProviderInterface
{
	/** @return Iterator<Field> */
	public function getFields(Workspace $workspace): Iterator;

	public function getField(Workspace $workspace, int $fieldId): ?Field;

	/** @param array<string>|null $options */
	public function createField(
		User $author,
		Workspace $workspace,
		string $name,
		FieldTypeEnum $type,
		bool $required,
		?string $defaultValue,
		?array $options,
	): Field;

	/** @param array<string>|null $options */
	public function updateField(
		User $author,
		Field $field,
		string $name,
		FieldTypeEnum $type,
		bool $required,
		?string $defaultValue,
		?array $options,
	): Field;

	public function deleteField(User $author, Field $field): void;

	/**
	 * @param array<string>|null $options
	 * @return array<string>|null
	 */
	public function normalizeOptions(FieldTypeEnum $type, ?array $options): ?array;
}
