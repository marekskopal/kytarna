<?php

declare(strict_types=1);

namespace Kytarna\Service\Auth;

use Kytarna\Model\Entity\Workspace;
use RuntimeException;

final class SoleOwnerException extends RuntimeException
{
	/** @param list<Workspace> $workspaces */
	public function __construct(public readonly array $workspaces)
	{
		parent::__construct(
			'Cannot delete account — you own these workspaces: '
			. implode(', ', array_map(static fn (Workspace $w): string => $w->name, $workspaces))
			. '. Transfer ownership or delete the workspaces first.',
		);
	}

	/** @return list<string> */
	public function workspaceNames(): array
	{
		return array_map(static fn (Workspace $w): string => $w->name, $this->workspaces);
	}

	/** @return list<int> */
	public function workspaceIds(): array
	{
		return array_map(static fn (Workspace $w): int => $w->id, $this->workspaces);
	}
}
