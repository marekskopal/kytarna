<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\Enum\StatusTypeEnum;

/** @implements ArrayFactoryInterface<array{name: string, color: string, type: string, position?: ?int}> */
final readonly class StatusCreateDto implements ArrayFactoryInterface
{
	public function __construct(public string $name, public string $color, public StatusTypeEnum $type, public ?int $position,)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(
			name: $data['name'],
			color: $data['color'],
			type: StatusTypeEnum::from($data['type']),
			position: $data['position'] ?? null,
		);
	}
}
