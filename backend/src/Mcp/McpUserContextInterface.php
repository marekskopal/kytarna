<?php

declare(strict_types=1);

namespace Kytarna\Mcp;

use Kytarna\Model\Entity\User;

interface McpUserContextInterface
{
	public function setUser(User $user): void;

	public function getUser(): User;

	public function clear(): void;
}
