<?php

declare(strict_types=1);

namespace Kytario\Jobs\Handler;

use Kytario\Dto\EmailVerificationQueueDto;
use Kytario\Jobs\Message\ReceivedMessageInterface;
use Kytario\Service\Email\EmailFactory;
use Kytario\Service\Email\MailerFactory;
use Kytario\Service\Task\TaskServiceInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class EmailVerificationHandler implements JobHandler
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
		$payload = $this->taskService->getPayloadDto($message, EmailVerificationQueueDto::class);

		$email = $this->emailFactory->createEmailVerificationEmail(
			recipientEmail: $payload->recipientEmail,
			userName: $payload->userName,
			token: $payload->token,
			locale: $payload->locale,
		);

		try {
			$this->mailerFactory->create()->send($email);
			$this->logger->info('Email-verification email sent to ' . $payload->recipientEmail);
		} catch (Throwable $e) {
			$this->logger->error('Failed to send email-verification email: ' . $e->getMessage());

			throw $e;
		}
	}
}
