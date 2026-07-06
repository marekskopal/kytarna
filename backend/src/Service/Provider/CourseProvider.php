<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use DateTimeImmutable;
use Iterator;
use Kytarna\Model\Entity\Course;
use Kytarna\Model\Entity\Enum\EventTypeEnum;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Model\Repository\CourseRepository;
use Kytarna\Validator\TextFieldValidator;

final readonly class CourseProvider implements CourseProviderInterface
{
	public function __construct(
		private CourseRepository $courseRepository,
		private WorkflowProviderInterface $workflowProvider,
		private EventProviderInterface $eventProvider,
		private CoursePrefixGeneratorInterface $prefixGenerator,
	) {
	}

	/** @return Iterator<Course> */
	public function getCourses(Workspace $workspace): Iterator
	{
		return $this->courseRepository->findCoursesByWorkspace($workspace->id);
	}

	public function getCourse(Workspace $workspace, int $courseId): ?Course
	{
		return $this->courseRepository->findCourse($workspace->id, $courseId);
	}

	public function createCourse(User $author, Workspace $workspace, string $name, ?string $description): Course
	{
		$name = TextFieldValidator::validateName($name, 'Course');
		$description = TextFieldValidator::validateDescription($description);
		$now = new DateTimeImmutable();
		$prefix = $this->prefixGenerator->generate($workspace, $name, null);
		$course = new Course(workspace: $workspace, name: $name, prefix: $prefix, description: $description);
		$course->createdAt = $now;
		$course->updatedAt = $now;

		$this->courseRepository->persist($course);

		$this->workflowProvider->createDefaultWorkflow($course);

		$this->eventProvider->recordEvent($author, $course, EventTypeEnum::CourseCreated, ['name' => $name]);

		return $course;
	}

	public function updateCourse(User $author, Course $course, string $name, ?string $description): Course
	{
		$name = TextFieldValidator::validateName($name, 'Course');
		$description = TextFieldValidator::validateDescription($description);
		if ($name !== $course->name) {
			$course->prefix = $this->prefixGenerator->generate($course->workspace, $name, $course->id);
		}
		$course->name = $name;
		$course->description = $description;
		$course->updatedAt = new DateTimeImmutable();
		$this->courseRepository->persist($course);

		$this->eventProvider->recordEvent($author, $course, EventTypeEnum::CourseUpdated, ['name' => $name]);

		return $course;
	}

	public function deleteCourse(Course $course): void
	{
		$this->courseRepository->delete($course);
	}
}
