<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Service\Provider\Enum\BulkOpEnum;

interface BulkLectureProviderInterface
{
	/**
	 * Apply one operation to many lectures in a single workspace-scoped batch.
	 *
	 * Per-lecture failures (not found, out of workspace, status mismatch, validation) are returned
	 * as `skipped` so partial success is observable. A single `LecturesBulkUpdated` Event row is
	 * written for the whole batch (per-lecture events from inner providers are suppressed).
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
