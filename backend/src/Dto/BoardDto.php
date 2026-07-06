<?php

declare(strict_types=1);

namespace Kytarna\Dto;

final readonly class BoardDto
{
	/**
	 * @param list<string> $statuses Fixed learning-status values (To Learn / Learning / Mastered), in order.
	 * @param list<LectureDto> $lectures
	 * @param list<SongDto> $songs Songs attached to this course.
	 */
	public function __construct(public CourseDto $course, public array $statuses, public array $lectures, public array $songs = [],)
	{
	}
}
