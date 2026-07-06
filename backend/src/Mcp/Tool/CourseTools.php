<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Tool;

use Kytarna\Mcp\Dto\McpCourseDto;
use Kytarna\Mcp\Dto\McpCourseListDto;
use Kytarna\Mcp\McpUserContextInterface;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Service\Provider\CourseProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Mcp\Capability\Attribute\McpTool;
use RuntimeException;

final readonly class CourseTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private CourseProviderInterface $courseProvider,
		private WorkspaceProviderInterface $workspaceProvider,
	) {
	}

	/** List all courses belonging to the user's current workspace. */
	#[McpTool(name: 'list_courses', description: 'List all courses in the current workspace')]
	public function listCourses(): McpCourseListDto
	{
		$workspace = $this->requireWorkspace();

		$courses = [];
		foreach ($this->courseProvider->getCourses($workspace) as $course) {
			$courses[] = McpCourseDto::fromEntity($course);
		}

		return new McpCourseListDto($courses);
	}

	/**
	 * Find a course by case-insensitive name match. Returns null if not found.
	 * Use this before creating a course to avoid duplicates.
	 *
	 * @param string $name Course name to search for (case-insensitive, exact match)
	 */
	#[McpTool(
		name: 'find_course_by_name',
		description: 'Find a course in the current workspace by name (case-insensitive, exact match). Returns null if not found.',
	)]
	public function findCourseByName(string $name): ?McpCourseDto
	{
		$workspace = $this->requireWorkspace();
		$needle = mb_strtolower($name);
		foreach ($this->courseProvider->getCourses($workspace) as $course) {
			if (mb_strtolower($course->name) === $needle) {
				return McpCourseDto::fromEntity($course);
			}
		}

		return null;
	}

	/**
	 * Get a single course by ID.
	 *
	 * @param int $courseId Course ID
	 */
	#[McpTool(name: 'get_course', description: 'Get a single course by ID')]
	public function getCourse(int $courseId): McpCourseDto
	{
		$workspace = $this->requireWorkspace();
		$course = $this->courseProvider->getCourse($workspace, $courseId);
		if ($course === null) {
			throw new RuntimeException(sprintf('Course %d not found.', $courseId));
		}

		return McpCourseDto::fromEntity($course);
	}

	/**
	 * Create a new course in the current workspace. A default workflow with statuses "To Learn", "Learning", "Mastered"
	 * is automatically created. Call find_course_by_name first to avoid duplicates.
	 *
	 * @param string $name Course name
	 * @param string|null $description Optional course description
	 */
	#[McpTool(
		name: 'create_course',
		description: 'Create a new course in the current workspace with the default To Learn / Learning / Mastered workflow',
	)]
	public function createCourse(string $name, ?string $description = null): McpCourseDto
	{
		$workspace = $this->requireWorkspace();
		$course = $this->courseProvider->createCourse($this->userContext->getUser(), $workspace, $name, $description);

		return McpCourseDto::fromEntity($course);
	}

	/**
	 * Delete a course and all its lectures and workflow data.
	 *
	 * @param int $courseId Course ID
	 */
	#[McpTool(name: 'delete_course', description: 'Delete a course (irreversible — removes all its lectures)')]
	public function deleteCourse(int $courseId): string
	{
		$workspace = $this->requireWorkspace();
		$course = $this->courseProvider->getCourse($workspace, $courseId);
		if ($course === null) {
			throw new RuntimeException(sprintf('Course %d not found.', $courseId));
		}

		$this->courseProvider->deleteCourse($course);

		return 'Course deleted.';
	}

	private function requireWorkspace(): Workspace
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->userContext->getUser());
		if ($workspace === null) {
			throw new RuntimeException('No active workspace. Create one in the Kytarna app first.');
		}

		return $workspace;
	}
}
