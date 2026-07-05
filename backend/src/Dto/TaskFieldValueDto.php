<?php

declare(strict_types=1);

namespace Ukolio\Dto;

final readonly class TaskFieldValueDto
{
	public function __construct(public int $fieldId, public ?string $value,)
	{
	}
}
