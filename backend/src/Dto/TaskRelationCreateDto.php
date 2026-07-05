<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use RuntimeException;
use Ukolio\Model\Entity\Enum\TaskRelationTypeEnum;

/** @implements ArrayFactoryInterface<array{targetTaskId: int, type: string}> */
final readonly class TaskRelationCreateDto implements ArrayFactoryInterface
{
	public function __construct(public int $targetTaskId, public TaskRelationTypeEnum $type,)
	{
	}

	public static function fromArray(array $data): static
	{
		$type = TaskRelationTypeEnum::tryFrom($data['type']);
		if ($type === null) {
			throw new RuntimeException(sprintf(
				'Invalid relation type. Valid values: %s',
				implode(', ', array_column(TaskRelationTypeEnum::cases(), 'value')),
			));
		}

		return new self(targetTaskId: $data['targetTaskId'], type: $type);
	}
}
