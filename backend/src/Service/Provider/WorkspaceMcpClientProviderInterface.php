<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Kytarna\Dto\WorkspaceMcpClientDto;
use Kytarna\Model\Entity\Workspace;

interface WorkspaceMcpClientProviderInterface
{
	/** @return list<WorkspaceMcpClientDto> */
	public function getClientsForWorkspace(Workspace $workspace): array;

	/** Revokes every authorization the workspace's members granted to the client. Returns the number of rows revoked. */
	public function revokeClient(Workspace $workspace, string $clientId): int;
}
