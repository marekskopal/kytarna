<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Iterator;
use Ukolio\Model\Entity\Priority;
use Ukolio\Model\Entity\Workspace;

interface PriorityProviderInterface
{
	/** @return Iterator<Priority> */
	public function getPriorities(Workspace $workspace): Iterator;

	public function getPriority(Workspace $workspace, int $priorityId): ?Priority;

	public function findPriorityByName(Workspace $workspace, string $name): ?Priority;

	public function getDefaultForWorkspace(Workspace $workspace): ?Priority;

	public function createPriority(Workspace $workspace, string $name, string $color, bool $isDefault): Priority;

	public function updatePriority(Priority $priority, string $name, string $color, bool $isDefault): Priority;

	public function movePriority(Priority $priority, int $newPosition): Priority;

	public function deletePriority(Priority $priority): void;
}
