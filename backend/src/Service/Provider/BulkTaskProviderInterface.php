<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Service\Provider\Enum\BulkOpEnum;

interface BulkTaskProviderInterface
{
	/**
	 * Apply one operation to many tasks in a single workspace-scoped batch.
	 *
	 * Per-task failures (not found, out of workspace, status mismatch, validation) are returned
	 * as `skipped` so partial success is observable. A single `TasksBulkUpdated` Event row is
	 * written for the whole batch (per-task events from inner providers are suppressed).
	 *
	 * @param list<int> $ids
	 * @param array<string, mixed> $payload
	 * @return array{
	 *     succeeded: list<int>,
	 *     skipped: list<array{id: int, reason: string}>,
	 * }
	 */
	public function execute(User $actor, Workspace $workspace, BulkOpEnum $op, array $ids, array $payload): array;
}
