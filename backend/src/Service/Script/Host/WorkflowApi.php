<?php

declare(strict_types=1);

namespace Kytario\Service\Script\Host;

/**
 * Returned by `kytario.workflow(projectId)`; exposes the project's ordered statuses.
 */
final readonly class WorkflowApi
{
	/** @param list<array<string, mixed>> $statuses */
	public function __construct(private array $statuses)
	{
	}

	/** @return list<array<string, mixed>> */
	public function statuses(): array
	{
		return $this->statuses;
	}
}
