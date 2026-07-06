<?php

declare(strict_types=1);

namespace Kytarna\Dto;

final readonly class SongWatchersDto
{
	/** @param list<SongWatcherDto> $watchers */
	public function __construct(public array $watchers, public bool $watching,)
	{
	}
}
