<?php

declare(strict_types=1);

namespace Kytario\Service\Search\Dto;

final readonly class SearchFiltersDto
{
	/** @param list<int>|null $statusIds */
	public function __construct(public ?int $projectId = null, public ?array $statusIds = null, public bool $onlyActive = false,)
	{
	}
}
