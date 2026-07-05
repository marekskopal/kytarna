<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Ukolio\Model\Entity\Workspace;

interface ProjectPrefixGeneratorInterface
{
	public function generate(Workspace $workspace, string $name, ?int $excludeProjectId): string;
}
