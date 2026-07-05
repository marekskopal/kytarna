<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use Kytario\Model\Entity\Workspace;

interface ProjectPrefixGeneratorInterface
{
	public function generate(Workspace $workspace, string $name, ?int $excludeProjectId): string;
}
