<?php

declare(strict_types=1);

namespace Kytario\Mcp\Dto;

use Kytario\Service\Search\Dto\SearchHitDto;

final readonly class McpSearchHitDto
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

	public static function fromDto(SearchHitDto $hit): self
	{
		return new self(
			id: $hit->id,
			code: $hit->code,
			projectId: $hit->projectId,
			statusId: $hit->statusId,
			name: $hit->name,
			snippet: $hit->snippet,
			matchedIn: $hit->matchedIn,
		);
	}
}
