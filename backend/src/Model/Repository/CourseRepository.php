<?php

declare(strict_types=1);

namespace Kytarna\Model\Repository;

use Iterator;
use Kytarna\Model\Entity\Course;
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<Course> */
final class CourseRepository extends AbstractRepository
{
	/** @return Iterator<Course> */
	public function findCoursesByWorkspace(int $workspaceId): Iterator
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->orderBy('id', 'DESC')
			->fetchAll();
	}

	public function findCourse(int $workspaceId, int $courseId): ?Course
	{
		return $this->findOne(['workspace_id' => $workspaceId, 'id' => $courseId]);
	}

	public function findByWorkspaceAndPrefix(int $workspaceId, string $prefix): ?Course
	{
		return $this->findOne(['workspace_id' => $workspaceId, 'prefix' => $prefix]);
	}

	/** @return list<string> */
	public function findPrefixesInWorkspace(int $workspaceId, ?int $excludeCourseId): array
	{
		$prefixes = [];
		foreach ($this->findCoursesByWorkspace($workspaceId) as $course) {
			if ($excludeCourseId !== null && $course->id === $excludeCourseId) {
				continue;
			}
			$prefixes[] = $course->prefix;
		}
		return $prefixes;
	}
}
