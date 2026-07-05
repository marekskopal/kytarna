<?php

declare(strict_types=1);

namespace Kytario\Service\Realtime;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Throwable;
use Kytario\Model\Entity\Enum\EventTypeEnum;
use const JSON_THROW_ON_ERROR;

final readonly class RealtimePublisher implements RealtimePublisherInterface
{
	public const string TopicPrefix = 'kytario/workspaces/';

	/** Per-user private topic — only the user's own Mercure JWT authorizes it (see MercureCookieIssuer). */
	public const string UserTopicPrefix = 'kytario/users/';

	public function __construct(
		private HubInterface $hub,
		private RealtimeOriginContextInterface $originContext,
		private LoggerInterface $logger,
	) {
	}

	public function publish(
		EventTypeEnum $type,
		int $workspaceId,
		?int $projectId = null,
		?int $taskId = null,
		?int $commentId = null,
		?int $fileId = null,
		?int $relationId = null,
	): void {
		$this->send(self::TopicPrefix . $workspaceId, $type, [
			'workspaceId' => $workspaceId,
			'projectId' => $projectId,
			'taskId' => $taskId,
			'commentId' => $commentId,
			'fileId' => $fileId,
			'relationId' => $relationId,
			'userId' => null,
		]);
	}

	public function publishToUser(
		EventTypeEnum $type,
		int $userId,
		?int $workspaceId = null,
		?int $projectId = null,
		?int $taskId = null,
	): void {
		// Addressed to the recipient's private topic so other workspace members never see the ping.
		$this->send(self::UserTopicPrefix . $userId, $type, [
			'workspaceId' => $workspaceId,
			'projectId' => $projectId,
			'taskId' => $taskId,
			'commentId' => null,
			'fileId' => null,
			'relationId' => null,
			'userId' => $userId,
		]);
	}

	/** @param array<string, mixed> $ids */
	private function send(string $topic, EventTypeEnum $type, array $ids): void
	{
		$data = json_encode([
			'type' => $type->value,
			...$ids,
			'originClientId' => $this->originContext->get(),
		], JSON_THROW_ON_ERROR);

		try {
			// Private updates are delivered only to subscribers whose JWT authorizes this exact topic
			// (see MercureCookieIssuer). Without this flag every update is public and the per-topic
			// authorization is bypassed entirely.
			$this->hub->publish(new Update($topic, $data, private: true));
		} catch (Throwable $e) {
			// Realtime is best-effort: a hub outage must never break the mutation that triggered it.
			$this->logger->warning('Mercure publish failed: ' . $e->getMessage(), ['exception' => $e]);
		}
	}
}
