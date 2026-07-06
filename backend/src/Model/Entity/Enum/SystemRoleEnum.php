<?php

declare(strict_types=1);

namespace Kytarna\Model\Entity\Enum;

enum SystemRoleEnum: string
{
	case User = 'User';
	case SystemAdmin = 'SystemAdmin';
}
