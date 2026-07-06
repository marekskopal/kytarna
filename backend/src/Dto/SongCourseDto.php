<?php

declare(strict_types=1);

namespace Kytarna\Dto;

/**
 * Attach a song to a course (courseId set) or detach it (courseId null).
 *
 * @implements ArrayFactoryInterface<array{courseId?: ?int}>
 */
final readonly class SongCourseDto implements ArrayFactoryInterface
{
	public function __construct(public ?int $courseId)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(courseId: $data['courseId'] ?? null);
	}
}
