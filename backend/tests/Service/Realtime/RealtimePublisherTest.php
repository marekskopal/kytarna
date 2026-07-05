<?php

declare(strict_types=1);

namespace Kytario\Tests\Service\Realtime;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Update;
use Kytario\Model\Entity\Enum\EventTypeEnum;
use Kytario\Service\Realtime\RealtimeOriginContext;
use Kytario\Service\Realtime\RealtimePublisher;
use const JSON_THROW_ON_ERROR;

#[CoversClass(RealtimePublisher::class)]
#[CoversClass(RealtimeOriginContext::class)]
final class RealtimePublisherTest extends TestCase
{
	public function testPublishWritesWorkspaceTopicAndIdsPayload(): void
	{
		$hub = new RecordingMercureHub();
		$origin = new RealtimeOriginContext();
		$publisher = new RealtimePublisher($hub, $origin, new NullLogger());

		$publisher->publish(type: EventTypeEnum::TaskCreated, workspaceId: 42, projectId: 7, taskId: 123);

		self::assertCount(1, $hub->updates);
		self::assertSame(['kytario/workspaces/42'], $hub->updates[0]->getTopics());
		// Updates MUST be private so Mercure enforces the per-workspace subscriber-JWT
		// authorization; a public update leaks to any (even anonymous) subscriber.
		self::assertTrue($hub->updates[0]->isPrivate());

		$payload = json_decode($hub->updates[0]->getData(), associative: true, flags: JSON_THROW_ON_ERROR);
		self::assertIsArray($payload);
		self::assertSame('TaskCreated', $payload['type']);
		self::assertSame(42, $payload['workspaceId']);
		self::assertSame(7, $payload['projectId']);
		self::assertSame(123, $payload['taskId']);
		self::assertNull($payload['commentId']);
		self::assertNull($payload['fileId']);
		self::assertNull($payload['relationId']);
		self::assertNull($payload['originClientId']);
	}

	public function testPublishToUserWritesPrivateUserTopic(): void
	{
		$hub = new RecordingMercureHub();
		$publisher = new RealtimePublisher($hub, new RealtimeOriginContext(), new NullLogger());

		$publisher->publishToUser(EventTypeEnum::NotificationCreated, userId: 99, workspaceId: 42, projectId: 7, taskId: 123);

		self::assertCount(1, $hub->updates);
		// Addressed to the recipient's own topic — not the workspace topic — so other members never see it.
		self::assertSame(['kytario/users/99'], $hub->updates[0]->getTopics());
		self::assertTrue($hub->updates[0]->isPrivate());

		$payload = json_decode($hub->updates[0]->getData(), associative: true, flags: JSON_THROW_ON_ERROR);
		self::assertIsArray($payload);
		self::assertSame('NotificationCreated', $payload['type']);
		self::assertSame(99, $payload['userId']);
		self::assertSame(123, $payload['taskId']);
	}

	public function testPublishIncludesOriginClientIdFromContext(): void
	{
		$hub = new RecordingMercureHub();
		$origin = new RealtimeOriginContext();
		$origin->set('tab-uuid-7777');
		$publisher = new RealtimePublisher($hub, $origin, new NullLogger());

		$publisher->publish(type: EventTypeEnum::TaskCommentAdded, workspaceId: 1, projectId: 2, taskId: 3, commentId: 9);

		$payload = json_decode($hub->updates[0]->getData(), associative: true, flags: JSON_THROW_ON_ERROR);
		self::assertIsArray($payload);
		self::assertSame('tab-uuid-7777', $payload['originClientId']);
		self::assertSame(9, $payload['commentId']);
	}

	public function testHubExceptionsAreSwallowed(): void
	{
		$hub = new class implements HubInterface {
			public function publish(Update $update): string
			{
				throw new \RuntimeException('Hub down');
			}

			public function getPublicUrl(): string
			{
				return '';
			}

			public function getFactory(): ?TokenFactoryInterface
			{
				return null;
			}
		};

		$publisher = new RealtimePublisher($hub, new RealtimeOriginContext(), new NullLogger());

		// Must not throw — realtime is best-effort.
		$publisher->publish(EventTypeEnum::TaskCreated, workspaceId: 1, projectId: 2, taskId: 3);
		$this->expectNotToPerformAssertions();
	}
}
