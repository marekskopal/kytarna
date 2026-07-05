<?php

declare(strict_types=1);

namespace Kytario\Dto;

final readonly class LectureWatchersDto
{
	/** @param list<LectureWatcherDto> $watchers */
	public function __construct(public array $watchers, public bool $watching,)
	{
	}
}
