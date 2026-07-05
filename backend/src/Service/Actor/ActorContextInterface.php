<?php

declare(strict_types=1);

namespace Kytario\Service\Actor;

use Kytario\Model\Entity\Enum\ActorTypeEnum;

/**
 * Per-request scope flag for who initiated the current action.
 *
 * Defaults to {@see ActorTypeEnum::Human}. MCP entrypoints flip this to
 * {@see ActorTypeEnum::Agent} and attach the OAuth client identifier so that
 * downstream providers (lectures, events) can tag agent-originated rows.
 */
interface ActorContextInterface
{
	public function setHuman(): void;

	public function setAgent(string $clientId, string $clientName): void;

	public function getActorType(): ActorTypeEnum;

	public function isAgent(): bool;

	public function getMcpClientId(): ?string;

	public function getMcpClientName(): ?string;
}
