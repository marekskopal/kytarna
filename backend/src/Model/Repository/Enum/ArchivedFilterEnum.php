<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository\Enum;

enum ArchivedFilterEnum: string
{
	case Active = 'active';
	case Archived = 'archived';
	case All = 'all';
}
