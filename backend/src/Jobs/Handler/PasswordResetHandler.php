<?php

declare(strict_types=1);

namespace Kytarna\Jobs\Handler;

use Kytarna\Dto\PasswordResetQueueDto;
use Kytarna\Jobs\Message\ReceivedMessageInterface;
use Kytarna\Service\Email\EmailFactory;
use Kytarna\Service\Email\MailerFactory;
use Kytarna\Service\Payload\PayloadServiceInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class PasswordResetHandler implements JobHandler
{
	public function __construct(
		private LoggerInterface $logger,
		private PayloadServiceInterface $payloadService,
		private MailerFactory $mailerFactory,
		private EmailFactory $emailFactory,
	) {
	}

	public function handle(ReceivedMessageInterface $message): void
	{
		$payload = $this->payloadService->getPayloadDto($message, PasswordResetQueueDto::class);

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
