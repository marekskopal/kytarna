<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity\Enum;

enum RecurrenceEndTypeEnum: string
{
	case Never = 'Never';
	case OnDate = 'OnDate';
	case AfterCount = 'AfterCount';
}
