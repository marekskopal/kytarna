<?php

declare(strict_types=1);

namespace Ukolio\Service\Search\Dto;

final readonly class SearchResultDto
{
	/** @param list<SearchHitDto> $hits */
	public function __construct(public array $hits, public int $estimatedTotalHits, public int $processingTimeMs,)
	{
	}
}
