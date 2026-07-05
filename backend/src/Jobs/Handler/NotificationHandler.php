<?php

declare(strict_types=1);

namespace Kytario\Jobs\Handler;

use Kytario\Dto\NotificationEmailQueueDto;
use Kytario\Jobs\Message\ReceivedMessageInterface;
use Kytario\Service\Email\EmailFactory;
use Kytario\Service\Email\MailerFactory;
use Kytario\Service\Task\TaskServiceInterface;
use Psr\Log\LoggerInterface;
use Throwable;

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
