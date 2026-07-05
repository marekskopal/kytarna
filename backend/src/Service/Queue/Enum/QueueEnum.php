<?php

declare(strict_types=1);

namespace Ukolio\Service\Queue\Enum;

enum QueueEnum: string
{
	case Invitation = 'invitation';
	case EmailVerification = 'email-verification';
	case PasswordReset = 'password-reset';
	case SearchReindex = 'search-reindex';
	case ScriptRun = 'script-run';
	case Notification = 'notification';
	case RecurringTaskSpawn = 'recurring-task-spawn';
}
