<?php

declare(strict_types=1);

namespace Ukolio\Mcp;

use Ukolio\Model\Entity\User;

interface McpUserContextInterface
{
	public function setUser(User $user): void;

	public function getUser(): User;

	public function clear(): void;
}
