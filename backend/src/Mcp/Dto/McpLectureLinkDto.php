<?php

declare(strict_types=1);

namespace Kytario\Mcp\Dto;

use Kytario\Model\Entity\LectureLink;

final readonly class McpLectureLinkDto
{
	public function __construct(
		public int $id,
		public int $lectureId,
		public string $url,
		public ?string $label,
		public string $kind,
		public ?int $timestampSeconds,
	) {
	}

	public static function fromEntity(LectureLink $link): self
	{
		return new self(
			id: $link->id,
			lectureId: $link->lecture->id,
			url: $link->url,
			label: $link->label,
			kind: $link->kind->value,
			timestampSeconds: $link->timestampSeconds,
		);
	}
}
