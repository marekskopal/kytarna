<?php

declare(strict_types=1);

namespace Ukolio\Dto;

/** @implements ArrayFactoryInterface<array{fieldIds: list<int>}> */
final readonly class ProjectFieldsUpdateDto implements ArrayFactoryInterface
{
	/** @param list<int> $fieldIds */
	public function __construct(public array $fieldIds)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(fieldIds: $data['fieldIds']);
	}
}
