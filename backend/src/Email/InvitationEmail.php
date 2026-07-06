<?php

declare(strict_types=1);

namespace Kytarna\Email;

use const ENT_QUOTES;

final readonly class InvitationEmail
{
	/** @param array<string, string> $t */
	public static function getHtml(string $inviterName, string $workspaceName, string $acceptUrl, array $t): string
	{
		$inviter = htmlspecialchars($inviterName, ENT_QUOTES, 'UTF-8');
		$workspace = htmlspecialchars($workspaceName, ENT_QUOTES, 'UTF-8');
		$url = htmlspecialchars($acceptUrl, ENT_QUOTES, 'UTF-8');

		$intro = strtr($t['intro'] ?? '', ['{inviter}' => '<strong>' . $inviter . '</strong>', '{workspace}' => $workspace]);
		$action = $t['action'] ?? '';
		$title = $t['title'] ?? 'Workspace invitation';
		$button = $t['button'] ?? 'Accept invitation';
		$fallback = $t['fallback'] ?? '';
		$expires = $t['expires'] ?? '';

		$fontStyle = 'font-family: Inter, Helvetica, Arial, sans-serif; font-size: 16px; line-height: 1.5; color: #1f2937;';
		$buttonStyle = 'display: inline-block; padding: 12px 24px; background-color: #2563eb; color: #ffffff; text-decoration: none; border-radius: 8px;';

		return <<<HTML
<html>
<body style="{$fontStyle} background-color:#f3f4f6; padding: 32px;">
	<div style="max-width: 560px; margin: 0 auto; padding: 32px; background-color: #ffffff; border-radius: 12px;">
		<h1 style="margin-top: 0;">{$title}</h1>
		<p>{$intro}</p>
		<p>{$action}</p>
		<p style="text-align: center; margin: 32px 0;">
			<a href="{$url}" style="{$buttonStyle}">{$button}</a>
		</p>
		<p style="color: #6b7280; font-size: 14px;">{$fallback}<br><a href="{$url}">{$url}</a></p>
		<p style="color: #6b7280; font-size: 14px;">{$expires}</p>
	</div>
</body>
</html>
HTML;
	}
}
