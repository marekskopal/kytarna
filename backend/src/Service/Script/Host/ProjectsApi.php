<?php

declare(strict_types=1);

namespace Kytario\Service\Script\Host;

use Kytario\Service\Provider\ProjectProviderInterface;

/**
 * Exposed to JS as `kytario.projects`.
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
