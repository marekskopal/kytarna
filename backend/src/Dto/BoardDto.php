<?php

declare(strict_types=1);

namespace Kytario\Dto;

final readonly class BoardDto
{
	/**
	 * @param list<StatusDto> $statuses
	 * @param list<LectureDto> $lectures
	 */
	public function __construct(public CourseDto $course, public WorkflowDto $workflow, public array $statuses, public array $lectures,)
	{
	}
}
