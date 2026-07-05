<?php

declare(strict_types=1);

namespace Kytario\Dto;

final readonly class LectureListDto
{
	/** @param list<LectureListItemDto> $lectures */
	public function __construct(public array $lectures, public int $count,)
	{
	}
}
