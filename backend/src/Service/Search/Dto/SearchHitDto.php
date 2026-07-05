<?php

declare(strict_types=1);

namespace Ukolio\Service\Search\Dto;

final readonly class SearchHitDto
{
	public function __construct(
		public int $id,
		public string $code,
		public int $projectId,
		public int $statusId,
		public string $name,
		public ?string $snippet,
		public string $matchedIn,
	) {
	}
}
