<?php

declare(strict_types=1);

namespace Kytario\Service\Provider\Enum;

enum BulkOpEnum: string
{
	case Move = 'move';
	case Tag = 'tag';
	case Untag = 'untag';
	case Delete = 'delete';
}
