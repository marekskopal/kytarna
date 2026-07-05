<?php

declare(strict_types=1);

namespace Ukolio;

require_once __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Throwable;
use Ukolio\App\ApplicationFactory;
use Ukolio\Jobs\Handler\ScriptRunHandler;
use Ukolio\Jobs\Message\AmqpReceivedMessage;
use Ukolio\Service\Queue\Enum\QueueEnum;

/**
 * Dedicated consumer for the script-run queue. Runs as its own supervisord program in the backend
 * container, launched with `php -d extension=v8js.so` (see docker/supervisord.conf) so the sandbox
 * (ext-v8js) is loaded only for this process — the heavyweight V8/libnode runtime stays out of
 * FrankenPHP and the main amqp-consumer, which deliberately skips this queue.
 */
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

$queue = QueueEnum::ScriptRun;
$channel->queue_declare($queue->value, false, true, false, false);
$channel->basic_qos(0, 1, false);

$channel->basic_consume(
	$queue->value,
	'',
	false,
	false,
	false,
	false,
	static function (AMQPMessage $msg) use ($application, $logger): void {
		try {
			$logger->info('Handling script run message', ['queue' => QueueEnum::ScriptRun->value]);

			$handler = $application->container->get(ScriptRunHandler::class);
			assert($handler instanceof ScriptRunHandler);
			$handler->handle(new AmqpReceivedMessage($msg->getBody(), QueueEnum::ScriptRun->value));

			$msg->ack();
		} catch (Throwable $e) {
			$logger->error('Script run message failed: ' . $e->getMessage(), ['exception' => $e]);
			// Do not requeue: a poisoned script payload would loop forever. The ScriptRun row
			// (if created) already records the failure.
			$msg->nack(false, false);
		}

		$application->dbContext->getOrm()->getEntityCache()->clear();
		gc_collect_cycles();
	},
);

$channel->consume();

$channel->close();
$connection->close();
