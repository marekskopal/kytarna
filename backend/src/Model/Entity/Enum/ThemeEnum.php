<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity\Enum;

enum ThemeEnum: string
{
	case System = 'system';
	case Light = 'light';
	case Dark = 'dark';
}
