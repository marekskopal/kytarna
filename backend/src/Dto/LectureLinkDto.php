<?php

declare(strict_types=1);

namespace Kytarna\Dto;

use Kytarna\Model\Entity\LectureLink;
use const DATE_ATOM;

final readonly class LectureLinkDto
{
	public function __construct(
		public int $id,
		public int $lectureId,
		public string $url,
		public ?string $label,
		public string $kind,
		public ?int $timestampSeconds,
		public string $createdAt,
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
			createdAt: $link->createdAt->format(DATE_ATOM),
		);
	}
}
