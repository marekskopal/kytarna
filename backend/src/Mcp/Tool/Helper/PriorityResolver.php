<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Tool\Helper;

use RuntimeException;
use Ukolio\Model\Entity\Priority;
use Ukolio\Model\Entity\Project;
use Ukolio\Service\Provider\PriorityProviderInterface;

final readonly class PriorityResolver
{
	public function __construct(private PriorityProviderInterface $priorityProvider)
	{
	}

	public function resolve(Project $project, ?int $priorityId, ?string $priorityName): ?Priority
	{
		if ($priorityId !== null) {
			$priority = $this->priorityProvider->getPriority($project->workspace, $priorityId);
			if ($priority === null) {
				throw new RuntimeException(sprintf('Priority %d not found in this workspace.', $priorityId));
			}
			return $priority;
		}

		if ($priorityName !== null) {
			$priority = $this->priorityProvider->findPriorityByName($project->workspace, $priorityName);
			if ($priority === null) {
				throw new RuntimeException(sprintf('Priority "%s" not found in this workspace.', $priorityName));
			}
			return $priority;
		}

		return $this->priorityProvider->getDefaultForWorkspace($project->workspace);
	}
}
