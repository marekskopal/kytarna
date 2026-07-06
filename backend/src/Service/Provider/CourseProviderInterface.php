<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Iterator;
use Kytarna\Model\Entity\Course;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;

interface CourseProviderInterface
{
	/** @return Iterator<Course> */
	public function getCourses(Workspace $workspace): Iterator;

	public function getCourse(Workspace $workspace, int $courseId): ?Course;

	public function createCourse(User $author, Workspace $workspace, string $name, ?string $description): Course;

	public function updateCourse(User $author, Course $course, string $name, ?string $description): Course;

	public function deleteCourse(Course $course): void;
}
