<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider\Enum;

enum BulkOpEnum: string
{
	case Move = 'move';
	case Tag = 'tag';
	case Untag = 'untag';
	case Assign = 'assign';
	case Priority = 'priority';
	case Delete = 'delete';
}
