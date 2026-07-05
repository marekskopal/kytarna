<?php

declare(strict_types=1);

namespace Kytario\Service\Realtime;

interface RealtimeOriginContextInterface
{
	public function set(?string $clientId): void;

	public function get(): ?string;

	public function clear(): void;
}
