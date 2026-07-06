<?php

declare(strict_types=1);

namespace Kytarna\Dto;

use Kytarna\Model\Entity\Enum\LearningStatusEnum;
use RuntimeException;

/** @implements ArrayFactoryInterface<array{status: string, position: int}> */
final readonly class LectureMoveDto implements ArrayFactoryInterface
{
	public function __construct(public LearningStatusEnum $status, public int $position)
	{
	}

	public static function fromArray(array $data): static
	{
		$status = LearningStatusEnum::fromLoose($data['status'])
			?? throw new RuntimeException('Invalid status; expected To Learn, Learning or Mastered.');
		return new self(status: $status, position: $data['position']);
	}
}
