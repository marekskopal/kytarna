<?php

declare(strict_types=1);

namespace Ukolio\Jobs\Handler;

use Psr\Log\LoggerInterface;
use Throwable;
use Ukolio\Dto\NotificationEmailQueueDto;
use Ukolio\Jobs\Message\ReceivedMessageInterface;
use Ukolio\Service\Email\EmailFactory;
use Ukolio\Service\Email\MailerFactory;
use Ukolio\Service\Task\TaskServiceInterface;

final readonly class NotificationHandler implements JobHandler
{
	public function __construct(
		private LoggerInterface $logger,
		private TaskServiceInterface $taskService,
		private MailerFactory $mailerFactory,
		private EmailFactory $emailFactory,
	) {
	}

	public function handle(ReceivedMessageInterface $message): void
	{
		$payload = $this->taskService->getPayloadDto($message, NotificationEmailQueueDto::class);

		$email = $this->emailFactory->createNotificationEmail($payload);

		try {
			$this->mailerFactory->create()->send($email);
			$this->logger->info('Notification email sent to ' . $payload->recipientEmail);
		} catch (Throwable $e) {
			$this->logger->error('Failed to send notification email: ' . $e->getMessage());

			throw $e;
		}
	}
}
