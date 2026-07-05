<?php

declare(strict_types=1);

namespace Kytario\Mcp;

use Kytario\Model\Entity\User;
use RuntimeException;

final class McpUserContext implements McpUserContextInterface
{
	private ?User $user = null;

	public function setUser(User $user): void
	{
		$this->user = $user;
	}

	public function getUser(): User
	{
		return $this->user ?? throw new RuntimeException('MCP user context has not been initialized.');
	}

	public function clear(): void
	{
		$this->user = null;
	}
}
