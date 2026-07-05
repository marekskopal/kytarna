<?php

declare(strict_types=1);

namespace Ukolio\Email;

use const ENT_QUOTES;

final readonly class EmailVerificationEmail
{
	/** @param array<string, string> $t */
	public static function getHtml(string $userName, string $verifyUrl, array $t): string
	{
		$name = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
		$url = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');

		$greeting = strtr($t['greeting'] ?? '', ['{name}' => '<strong>' . $name . '</strong>']);
		$intro = $t['intro'] ?? '';
		$title = $t['title'] ?? 'Verify your email';
		$button = $t['button'] ?? 'Verify email';
		$fallback = $t['fallback'] ?? '';
		$expires = $t['expires'] ?? '';
		$ignore = $t['ignore'] ?? '';

		$fontStyle = 'font-family: Inter, Helvetica, Arial, sans-serif; font-size: 16px; line-height: 1.5; color: #1f2937;';
		$buttonStyle = 'display: inline-block; padding: 12px 24px; background-color: #2563eb; color: #ffffff; text-decoration: none; border-radius: 8px;';

		return <<<HTML
<html>
<body style="{$fontStyle} background-color:#f3f4f6; padding: 32px;">
	<div style="max-width: 560px; margin: 0 auto; padding: 32px; background-color: #ffffff; border-radius: 12px;">
		<h1 style="margin-top: 0;">{$title}</h1>
		<p>{$greeting}</p>
		<p>{$intro}</p>
		<p style="text-align: center; margin: 32px 0;">
			<a href="{$url}" style="{$buttonStyle}">{$button}</a>
		</p>
		<p style="color: #6b7280; font-size: 14px;">{$fallback}<br><a href="{$url}">{$url}</a></p>
		<p style="color: #6b7280; font-size: 14px;">{$expires}</p>
		<p style="color: #6b7280; font-size: 14px;">{$ignore}</p>
	</div>
</body>
</html>
HTML;
	}
}
