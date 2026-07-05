<?php

declare(strict_types=1);

namespace Kytario\Model\Entity\Enum;

enum WorkspaceRoleEnum: string
{
	case Owner = 'Owner';
	case Admin = 'Admin';
	case Member = 'Member';
}
