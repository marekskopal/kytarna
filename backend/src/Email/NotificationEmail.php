<?php

declare(strict_types=1);

namespace Ukolio\Email;

use const ENT_QUOTES;

final readonly class NotificationEmail
{
	public static function getHtml(string $greeting, string $intro, string $taskUrl, string $button, string $fallback): string
	{
		$greetingHtml = htmlspecialchars($greeting, ENT_QUOTES, 'UTF-8');
		$introHtml = htmlspecialchars($intro, ENT_QUOTES, 'UTF-8');
		$url = htmlspecialchars($taskUrl, ENT_QUOTES, 'UTF-8');
		$buttonHtml = htmlspecialchars($button, ENT_QUOTES, 'UTF-8');
		$fallbackHtml = htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8');

		$fontStyle = 'font-family: Inter, Helvetica, Arial, sans-serif; font-size: 16px; line-height: 1.5; color: #1f2937;';
		$buttonStyle = 'display: inline-block; padding: 12px 24px; background-color: #2563eb; color: #ffffff; text-decoration: none; border-radius: 8px;';

		return <<<HTML
<html>
<body style="{$fontStyle} background-color:#f3f4f6; padding: 32px;">
	<div style="max-width: 560px; margin: 0 auto; padding: 32px; background-color: #ffffff; border-radius: 12px;">
		<p>{$greetingHtml}</p>
		<p>{$introHtml}</p>
		<p style="text-align: center; margin: 32px 0;">
			<a href="{$url}" style="{$buttonStyle}">{$buttonHtml}</a>
		</p>
		<p style="color: #6b7280; font-size: 14px;">{$fallbackHtml}<br><a href="{$url}">{$url}</a></p>
	</div>
</body>
</html>
HTML;
	}
}
