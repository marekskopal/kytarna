<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Ukolio\Dto\WorkspaceMcpClientDto;
use Ukolio\Model\Entity\Workspace;

interface WorkspaceMcpClientProviderInterface
{
	/** @return list<WorkspaceMcpClientDto> */
	public function getClientsForWorkspace(Workspace $workspace): array;

	/** Revokes every authorization the workspace's members granted to the client. Returns the number of rows revoked. */
	public function revokeClient(Workspace $workspace, string $clientId): int;
}
