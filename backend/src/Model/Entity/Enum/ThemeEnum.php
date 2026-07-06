<?php

declare(strict_types=1);

namespace Kytarna\Model\Entity\Enum;

enum ThemeEnum: string
{
	case System = 'system';
	case Light = 'light';
	case Dark = 'dark';
}
