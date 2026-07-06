<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Kytarna\Model\Entity\Course;
use Kytarna\Model\Repository\LectureRepository;
use Kytarna\Model\Repository\SongRepository;

final readonly class CourseSequenceProvider implements CourseSequenceProviderInterface
{
	public function __construct(private LectureRepository $lectureRepository, private SongRepository $songRepository,)
	{
	}

	public function next(Course $course): int
	{
		return max(
			$this->lectureRepository->maxSequenceNumber($course->id),
			$this->songRepository->maxSequenceNumber($course->id),
		) + 1;
	}
}
