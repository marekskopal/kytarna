<?php

declare(strict_types=1);

namespace Kytario\Tests\Service\Email;

use Kytario\Dto\NotificationEmailQueueDto;
use Kytario\Model\Entity\Enum\LocaleEnum;
use Kytario\Model\Entity\Enum\NotificationTypeEnum;
use Kytario\Service\Email\EmailFactory;
use Kytario\Service\Translator\TranslatorService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailFactory::class)]
final class EmailFactoryTest extends TestCase
{
	public function testInvitationEmailContainsTokenAcceptUrlAndIsLocalised(): void
	{
		putenv('PROXY_HOST=app.kytario.example');
		putenv('PROXY_PORT_SSL=443');
		putenv('EMAIL_FROM=no-reply@kytario.example');

		$translator = new TranslatorService(__DIR__ . '/../../../translations');
		$factory = new EmailFactory($translator);

		$email = $factory->createInvitationEmail(
			recipientEmail: 'invitee@example.com',
			workspaceName: 'Acme',
			inviterName: 'Inviter',
			token: 'raw-token-123',
			locale: LocaleEnum::Cs,
		);

		self::assertSame('no-reply@kytario.example', $email->getFrom()[0]->getAddress());
		self::assertSame('invitee@example.com', $email->getTo()[0]->getAddress());
		$subject = $email->getSubject();
		self::assertIsString($subject);
		self::assertStringContainsString('Acme', $subject);

		$html = $email->getHtmlBody();
		self::assertIsString($html);
		self::assertStringContainsString('raw-token-123', $html);
		self::assertStringContainsString('https://app.kytario.example/app/invitations/accept?token=raw-token-123', $html);
	}

	public function testPasswordResetEmailIncludesUrlAndToken(): void
	{
		putenv('PROXY_HOST=app.kytario.example');
		putenv('PROXY_PORT_SSL=443');
		putenv('EMAIL_FROM=no-reply@kytario.example');

		$translator = new TranslatorService(__DIR__ . '/../../../translations');
		$factory = new EmailFactory($translator);

		$email = $factory->createPasswordResetEmail(
			recipientEmail: 'reset@example.com',
			userName: 'Reset',
			token: 'reset-token',
			locale: LocaleEnum::En,
		);

		$html = $email->getHtmlBody();
		self::assertIsString($html);
		self::assertStringContainsString('reset-token', $html);
		self::assertStringContainsString('/app/reset-password?token=reset-token', $html);
	}

	public function testEmailVerificationEmailIncludesUrlAndToken(): void
	{
		putenv('PROXY_HOST=app.kytario.example');
		putenv('PROXY_PORT_SSL=443');
		putenv('EMAIL_FROM=no-reply@kytario.example');

		$translator = new TranslatorService(__DIR__ . '/../../../translations');
		$factory = new EmailFactory($translator);

		$email = $factory->createEmailVerificationEmail(
			recipientEmail: 'verify@example.com',
			userName: 'Verify',
			token: 'verify-token',
			locale: LocaleEnum::En,
		);

		$html = $email->getHtmlBody();
		self::assertIsString($html);
		self::assertStringContainsString('verify-token', $html);
		self::assertStringContainsString('/app/verify-email?token=verify-token', $html);
	}

	public function testNotificationEmailLinksToTheTaskWithRootRelativePath(): void
	{
		putenv('PROXY_HOST=app.kytario.example');
		putenv('PROXY_PORT_SSL=443');
		putenv('EMAIL_FROM=no-reply@kytario.example');

		$translator = new TranslatorService(__DIR__ . '/../../../translations');
		$factory = new EmailFactory($translator);

		$email = $factory->createNotificationEmail(new NotificationEmailQueueDto(
			recipientEmail: 'watcher@example.com',
			recipientName: 'Watcher',
			locale: LocaleEnum::En,
			type: NotificationTypeEnum::TaskAssigned,
			actorName: 'Carol',
			taskCode: 'UK-1',
			taskName: 'Ship it',
			projectId: 7,
			statusName: null,
			dueDate: null,
		));

		$html = $email->getHtmlBody();
		self::assertIsString($html);
		// SaaS layout serves the app under /app, so in-app deep links carry the prefix.
		self::assertStringContainsString('https://app.kytario.example/app/projects/7/board?task=UK-1', $html);
	}
}
