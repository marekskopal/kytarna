<?php

declare(strict_types=1);

namespace Ukolio\Service\Queue;

use JsonException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;
use Ukolio\Service\Queue\Enum\QueueEnum;
use const JSON_THROW_ON_ERROR;

final class QueuePublisher
{
	private ?AMQPStreamConnection $connection = null;

	private ?AMQPChannel $channel = null;

	public function publishMessage(object $message, QueueEnum $queueType): void
	{
		try {
			$payload = json_encode($message, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new RuntimeException('Failed to encode queue message to JSON: ' . $e->getMessage(), 0, $e);
		}

		$channel = $this->channel();
		$channel->queue_declare($queueType->value, false, true, false, false);
		$channel->basic_publish(
			new AMQPMessage($payload, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]),
			'',
			$queueType->value,
		);
	}

	public function close(): void
	{
		$this->channel?->close();
		$this->connection?->close();
		$this->channel = null;
		$this->connection = null;
	}

	private function channel(): AMQPChannel
	{
		if ($this->channel === null) {
			$this->connection = new AMQPStreamConnection(
				(string) getenv('RABBITMQ_HOST'),
				(int) getenv('RABBITMQ_PORT'),
				(string) getenv('RABBITMQ_USER'),
				(string) getenv('RABBITMQ_PASSWORD'),
			);
			$this->channel = $this->connection->channel();
		}

		return $this->channel;
	}
}
