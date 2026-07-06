<?php

declare(strict_types=1);

namespace Kytarna\Model\Entity\Enum;

enum WorkspaceRoleEnum: string
{
	case Owner = 'Owner';
	case Admin = 'Admin';
	case Member = 'Member';
}
