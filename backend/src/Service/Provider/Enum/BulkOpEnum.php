<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider\Enum;

enum BulkOpEnum: string
{
	case Move = 'move';
	case Tag = 'tag';
	case Untag = 'untag';
	case Delete = 'delete';
}
