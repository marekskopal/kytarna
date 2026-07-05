<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\Enum\FieldTypeEnum;

/** @implements ArrayFactoryInterface<array{name: string, type: string, required?: bool, defaultValue?: ?string, options?: ?array<string>}> */
final readonly class FieldCreateDto implements ArrayFactoryInterface
{
	/** @param list<string>|null $options */
	public function __construct(
		public string $name,
		public FieldTypeEnum $type,
		public bool $required,
		public ?string $defaultValue,
		public ?array $options,
	) {
	}

	public static function fromArray(array $data): static
	{
		$defaultValue = $data['defaultValue'] ?? null;
		if ($defaultValue !== null && trim($defaultValue) === '') {
			$defaultValue = null;
		}

		$rawOptions = $data['options'] ?? null;
		$options = $rawOptions !== null ? array_values($rawOptions) : null;

		return new self(
			name: $data['name'],
			type: FieldTypeEnum::tryFrom($data['type']) ?? FieldTypeEnum::Text,
			required: $data['required'] ?? false,
			defaultValue: $defaultValue,
			options: $options,
		);
	}
}
