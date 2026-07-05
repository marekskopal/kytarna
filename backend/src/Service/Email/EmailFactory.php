<?php

declare(strict_types=1);

namespace Kytario\Service\Email;

use Kytario\Dto\NotificationEmailQueueDto;
use Kytario\Email\EmailVerificationEmail;
use Kytario\Email\InvitationEmail;
use Kytario\Email\NotificationEmail;
use Kytario\Email\PasswordResetEmail;
use Kytario\Model\Entity\Enum\LocaleEnum;
use Kytario\Service\Translator\TranslatorServiceInterface;
use Symfony\Component\Mime\Email;

final readonly class EmailFactory
{
	private string $from;

	private string $appUrl;

	public function __construct(private TranslatorServiceInterface $translator)
	{
		$from = (string) getenv('EMAIL_FROM');
		$this->from = $from !== '' ? $from : 'no-reply@kytario.local';

		$host = (string) getenv('PROXY_HOST');
		$host = $host !== '' ? $host : 'localhost';
		$portSsl = (string) getenv('PROXY_PORT_SSL');
		$portSuffix = $portSsl === '' || $portSsl === '443' ? '' : ':' . $portSsl;
		$this->appUrl = 'https://' . $host . $portSuffix;
	}

	public function createInvitationEmail(
		string $recipientEmail,
		string $workspaceName,
		string $inviterName,
		string $token,
		LocaleEnum $locale,
	): Email {
		$acceptUrl = $this->appUrl . '/app/invitations/accept?token=' . urlencode($token);

		$subject = strtr(
			$this->translator->translate('email.subject.invitation', $locale),
			['{workspace}' => $workspaceName],
		);

		$html = InvitationEmail::getHtml(
			inviterName: $inviterName,
			workspaceName: $workspaceName,
			acceptUrl: $acceptUrl,
			t: $this->translator->section('email.invitation', $locale),
		);

		return new Email()
			->from($this->from)
			->to($recipientEmail)
			->subject($subject)
			->html($html);
	}

	public function createNotificationEmail(NotificationEmailQueueDto $payload): Email
	{
		$locale = $payload->locale;
		$typeKey = $payload->type->value;
		$taskCode = $payload->taskCode ?? '';

		// SaaS layout: the app lives under /app (the bare marketing site is at /), so an in-app
		// deep link must include the /app prefix. (On the open-source `public` branch this is root.)
		$taskUrl = $payload->projectId !== null
			? $this->appUrl . '/app/projects/' . $payload->projectId . '/board?task=' . urlencode($taskCode)
			: $this->appUrl . '/app';

		$replace = [
			'{actor}' => $payload->actorName ?? '',
			'{task}' => $taskCode,
			'{taskName}' => $payload->taskName ?? '',
			'{name}' => $payload->recipientName,
			'{status}' => $payload->statusName ?? '',
		];

		$subject = strtr($this->translator->translate('email.subject.notification.' . $typeKey, $locale), $replace);

		$t = $this->translator->section('email.notification', $locale);

		$html = NotificationEmail::getHtml(
			greeting: strtr($t['greeting'] ?? '', $replace),
			intro: strtr($t[$typeKey] ?? '', $replace),
			taskUrl: $taskUrl,
			button: $t['button'] ?? 'Open task',
			fallback: $t['fallback'] ?? '',
		);

		return new Email()
			->from($this->from)
			->to($payload->recipientEmail)
			->subject($subject)
			->html($html);
	}

	public function createPasswordResetEmail(string $recipientEmail, string $userName, string $token, LocaleEnum $locale,): Email
	{
		$resetUrl = $this->appUrl . '/app/reset-password?token=' . urlencode($token);

		$subject = $this->translator->translate('email.subject.passwordReset', $locale);

		$html = PasswordResetEmail::getHtml(
			userName: $userName,
			resetUrl: $resetUrl,
			t: $this->translator->section('email.passwordReset', $locale),
		);

		return new Email()
			->from($this->from)
			->to($recipientEmail)
			->subject($subject)
			->html($html);
	}

	public function createEmailVerificationEmail(string $recipientEmail, string $userName, string $token, LocaleEnum $locale,): Email
	{
		$verifyUrl = $this->appUrl . '/app/verify-email?token=' . urlencode($token);

		$subject = $this->translator->translate('email.subject.emailVerification', $locale);

		$html = EmailVerificationEmail::getHtml(
			userName: $userName,
			verifyUrl: $verifyUrl,
			t: $this->translator->section('email.emailVerification', $locale),
		);

		return new Email()
			->from($this->from)
			->to($recipientEmail)
			->subject($subject)
			->html($html);
	}
}
