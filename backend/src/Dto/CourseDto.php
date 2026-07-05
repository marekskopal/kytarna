<?php

declare(strict_types=1);

namespace Kytario\Dto;

use Kytario\Model\Entity\Course;
use const DATE_ATOM;

final readonly class CourseDto
{
	public function __construct(
		public int $id,
		public string $name,
		public string $prefix,
		public ?string $description,
		public string $createdAt,
		public string $updatedAt,
	) {
	}

	public static function fromEntity(Course $course): self
	{
		return new self(
			id: $course->id,
			name: $course->name,
			prefix: $course->prefix,
			description: $course->description,
			createdAt: $course->createdAt->format(DATE_ATOM),
			updatedAt: $course->updatedAt->format(DATE_ATOM),
		);
	}
}
