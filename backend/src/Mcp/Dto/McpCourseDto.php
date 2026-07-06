<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

use Kytarna\Model\Entity\Course;

final readonly class McpCourseDto
{
	public function __construct(public int $id, public string $name, public string $prefix, public ?string $description,)
	{
	}

	public static function fromEntity(Course $course): self
	{
		return new self(id: $course->id, name: $course->name, prefix: $course->prefix, description: $course->description);
	}
}
