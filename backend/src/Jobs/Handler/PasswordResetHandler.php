<?php

declare(strict_types=1);

namespace Ukolio\Jobs\Handler;

use Psr\Log\LoggerInterface;
use Throwable;
use Ukolio\Dto\PasswordResetQueueDto;
use Ukolio\Jobs\Message\ReceivedMessageInterface;
use Ukolio\Service\Email\EmailFactory;
use Ukolio\Service\Email\MailerFactory;
use Ukolio\Service\Task\TaskServiceInterface;

final readonly class PasswordResetHandler implements JobHandler
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
		$payload = $this->taskService->getPayloadDto($message, PasswordResetQueueDto::class);

		$email = $this->emailFactory->createPasswordResetEmail(
			recipientEmail: $payload->recipientEmail,
			userName: $payload->userName,
			token: $payload->token,
			locale: $payload->locale,
		);

		try {
			$this->mailerFactory->create()->send($email);
			$this->logger->info('Password-reset email sent to ' . $payload->recipientEmail);
		} catch (Throwable $e) {
			$this->logger->error('Failed to send password-reset email: ' . $e->getMessage());

			throw $e;
		}
	}
}
