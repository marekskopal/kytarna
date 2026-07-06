<?php

declare(strict_types=1);

namespace Kytarna\Dto;

final readonly class LectureWatchersDto
{
	/** @param list<LectureWatcherDto> $watchers */
	public function __construct(public array $watchers, public bool $watching,)
	{
	}
}
