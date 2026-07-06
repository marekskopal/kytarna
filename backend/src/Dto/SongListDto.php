<?php

declare(strict_types=1);

namespace Kytarna\Dto;

final readonly class SongListDto
{
	/** @param list<SongDto> $songs */
	public function __construct(public array $songs, public int $count)
	{
	}
}
