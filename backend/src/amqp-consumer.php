<?php

declare(strict_types=1);

namespace Ukolio;

require_once __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Throwable;
use Ukolio\App\ApplicationFactory;
use Ukolio\Jobs\Handler\EmailVerificationHandler;
use Ukolio\Jobs\Handler\InvitationHandler;
use Ukolio\Jobs\Handler\JobHandler;
use Ukolio\Jobs\Handler\NotificationHandler;
use Ukolio\Jobs\Handler\PasswordResetHandler;
use Ukolio\Jobs\Handler\RecurringTaskSpawnHandler;
use Ukolio\Jobs\Handler\SearchReindexHandler;
use Ukolio\Jobs\Message\AmqpReceivedMessage;
use Ukolio\Service\Queue\Enum\QueueEnum;

$application = ApplicationFactory::create();

$logger = $application->container->get(LoggerInterface::class);
assert($logger instanceof LoggerInterface);

$connection = new AMQPStreamConnection(
	(string) getenv('RABBITMQ_HOST'),
	(int) getenv('RABBITMQ_PORT'),
	(string) getenv('RABBITMQ_USER'),
	(string) getenv('RABBITMQ_PASSWORD'),
);
$channel = $connection->channel();

/** @var array<string, class-string<JobHandler>> $handlerMap */
$handlerMap = [
	QueueEnum::Invitation->value => InvitationHandler::class,
	QueueEnum::EmailVerification->value => EmailVerificationHandler::class,
	QueueEnum::PasswordReset->value => PasswordResetHandler::class,
	QueueEnum::SearchReindex->value => SearchReindexHandler::class,
	QueueEnum::Notification->value => NotificationHandler::class,
	QueueEnum::RecurringTaskSpawn->value => RecurringTaskSpawnHandler::class,
];

$prefetch = (int) getenv('BACKEND_AMQP_CONSUMER_PREFETCH');
if ($prefetch <= 0) {
	$prefetch = 10;
}

// The script-run queue is consumed by the dedicated script-worker (script-worker.php), which
// loads ext-v8js; this process runs without it and must not pick up those messages.
$queues = array_filter(QueueEnum::cases(), static fn (QueueEnum $queue): bool => $queue !== QueueEnum::ScriptRun);

foreach ($queues as $queue) {
	$channel->queue_declare($queue->value, false, true, false, false);
}
$channel->basic_qos(0, $prefetch, false);

foreach ($queues as $queue) {
	$channel->basic_consume(
		$queue->value,
		'',
		false,
		false,
		false,
		false,
		static function (AMQPMessage $msg) use ($application, $logger, $handlerMap): void {
			$queueName = (string) $msg->getRoutingKey();

			try {
				if (!isset($handlerMap[$queueName])) {
					throw new \InvalidArgumentException('Unhandled queue [' . $queueName . ']');
				}

				$logger->info('Handling queue message', ['queue' => $queueName]);

				$handler = $application->container->get($handlerMap[$queueName]);
				assert($handler instanceof JobHandler);
				$handler->handle(new AmqpReceivedMessage($msg->getBody(), $queueName));

				$msg->ack();
			} catch (Throwable $e) {
				$logger->error('Queue message failed: ' . $e->getMessage(), ['queue' => $queueName, 'exception' => $e]);
				$msg->nack(false, true);
			}

			$application->dbContext->getOrm()->getEntityCache()->clear();
			gc_collect_cycles();
		},
	);
}

$channel->consume();

$channel->close();
$connection->close();
