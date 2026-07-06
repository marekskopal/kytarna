<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Kytarna\Model\Entity\Workspace;

interface CoursePrefixGeneratorInterface
{
	public function generate(Workspace $workspace, string $name, ?int $excludeCourseId): string;
}
