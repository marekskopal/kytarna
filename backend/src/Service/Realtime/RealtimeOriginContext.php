<?php

declare(strict_types=1);

namespace Ukolio\Service\Realtime;

final class RealtimeOriginContext implements RealtimeOriginContextInterface
{
	private ?string $clientId = null;

	public function set(?string $clientId): void
	{
		$this->clientId = $clientId === '' ? null : $clientId;
	}

	public function get(): ?string
	{
		return $this->clientId;
	}

	public function clear(): void
	{
		$this->clientId = null;
	}
}
