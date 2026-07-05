<?php

declare(strict_types=1);

namespace Kytario\Service\Queue\Enum;

enum QueueEnum: string
{
	case Invitation = 'invitation';
	case EmailVerification = 'email-verification';
	case PasswordReset = 'password-reset';
	case Notification = 'notification';
}
