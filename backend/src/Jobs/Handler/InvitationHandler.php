<?php

declare(strict_types=1);

namespace Kytario\Jobs\Handler;

use Kytario\Dto\InvitationQueueDto;
use Kytario\Jobs\Message\ReceivedMessageInterface;
use Kytario\Service\Email\EmailFactory;
use Kytario\Service\Email\MailerFactory;
use Kytario\Service\Payload\PayloadServiceInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class InvitationHandler implements JobHandler
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
		$payload = $this->payloadService->getPayloadDto($message, InvitationQueueDto::class);

		$email = $this->emailFactory->createInvitationEmail(
			recipientEmail: $payload->recipientEmail,
			workspaceName: $payload->workspaceName,
			inviterName: $payload->inviterName,
			token: $payload->token,
			locale: $payload->locale,
		);

		try {
			$this->mailerFactory->create()->send($email);
			$this->logger->info('Invitation email sent to ' . $payload->recipientEmail);
		} catch (Throwable $e) {
			$this->logger->error('Failed to send invitation email: ' . $e->getMessage());

			throw $e;
		}
	}
}
