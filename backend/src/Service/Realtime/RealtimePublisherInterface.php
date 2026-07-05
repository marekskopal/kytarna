<?php

declare(strict_types=1);

namespace Kytario\Service\Realtime;

use Kytario\Model\Entity\Enum\EventTypeEnum;

interface RealtimePublisherInterface
{
	public function publish(
		EventTypeEnum $type,
		int $workspaceId,
		?int $projectId = null,
		?int $taskId = null,
		?int $commentId = null,
		?int $fileId = null,
		?int $relationId = null,
	): void;

	/** Publish to a single user's private topic (e.g. a notification ping), invisible to other members. */
	public function publishToUser(
		EventTypeEnum $type,
		int $userId,
		?int $workspaceId = null,
		?int $projectId = null,
		?int $taskId = null,
	): void;
}
