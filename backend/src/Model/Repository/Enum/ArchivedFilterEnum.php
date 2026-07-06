<?php

declare(strict_types=1);

namespace Kytarna\Model\Repository\Enum;

enum ArchivedFilterEnum: string
{
	case Active = 'active';
	case Archived = 'archived';
	case All = 'all';
}
