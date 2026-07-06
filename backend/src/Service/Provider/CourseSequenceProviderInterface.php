<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Kytarna\Model\Entity\Course;

/**
 * Allocates the next per-course sequence number for a `PREFIX-N` code. The counter is shared across
 * lectures and songs so a course never mints the same code for two different items.
 */
interface CourseSequenceProviderInterface
{
	public function next(Course $course): int;
}
