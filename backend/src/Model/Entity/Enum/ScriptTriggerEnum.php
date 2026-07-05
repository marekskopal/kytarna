<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity\Enum;

enum ScriptTriggerEnum: string
{
	case Manual = 'Manual';
	case Scheduled = 'Scheduled';
	case Event = 'Event';
}
