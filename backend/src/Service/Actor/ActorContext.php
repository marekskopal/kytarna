<?php

declare(strict_types=1);

namespace Ukolio\Service\Actor;

use Ukolio\Model\Entity\Enum\ActorTypeEnum;

final class ActorContext implements ActorContextInterface
{
	private ActorTypeEnum $actorType = ActorTypeEnum::Human;

	private ?string $clientId = null;

	private ?string $clientName = null;

	public function setHuman(): void
	{
		$this->actorType = ActorTypeEnum::Human;
		$this->clientId = null;
		$this->clientName = null;
	}

	public function setAgent(string $clientId, string $clientName): void
	{
		$this->actorType = ActorTypeEnum::Agent;
		$this->clientId = $clientId;
		$this->clientName = $clientName;
	}

	public function getActorType(): ActorTypeEnum
	{
		return $this->actorType;
	}

	public function isAgent(): bool
	{
		return $this->actorType === ActorTypeEnum::Agent;
	}

	public function getMcpClientId(): ?string
	{
		return $this->clientId;
	}

	public function getMcpClientName(): ?string
	{
		return $this->clientName;
	}
}
