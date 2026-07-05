<?php

declare(strict_types=1);

namespace Ukolio\Service\Script\Host;

use Ukolio\Service\Provider\ProjectProviderInterface;

/**
 * Exposed to JS as `ukolio.projects`.
 */
final readonly class ProjectsApi
{
	public function __construct(private ScriptRunContext $context, private ProjectProviderInterface $projects,)
	{
	}

	/** @return list<array<string, mixed>> */
	public function list(): array
	{
		$this->context->recordTaskApiCall();

		$out = [];
		foreach ($this->projects->getProjects($this->context->workspace) as $project) {
			$out[] = HostSerializer::project($project);
		}

		return $out;
	}
}
