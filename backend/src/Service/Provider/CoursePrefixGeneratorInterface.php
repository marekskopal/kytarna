<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use Kytario\Model\Entity\Workspace;

interface CoursePrefixGeneratorInterface
{
	public function generate(Workspace $workspace, string $name, ?int $excludeCourseId): string;
}
