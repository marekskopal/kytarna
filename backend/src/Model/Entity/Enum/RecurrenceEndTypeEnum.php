<?php

declare(strict_types=1);

namespace Kytario\Model\Entity\Enum;

enum RecurrenceEndTypeEnum: string
{
	case Never = 'Never';
	case OnDate = 'OnDate';
	case AfterCount = 'AfterCount';
}
