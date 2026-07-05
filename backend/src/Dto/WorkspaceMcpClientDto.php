<?php

declare(strict_types=1);

namespace Ukolio\Dto;

final readonly class WorkspaceMcpClientDto
{
	public function __construct(
		public string $clientId,
		public string $clientName,
		public string $firstSeenAt,
		public string $lastUsedAt,
		public int $activeTokens,
		public int $totalAuthorizations,
	) {
	}
}
