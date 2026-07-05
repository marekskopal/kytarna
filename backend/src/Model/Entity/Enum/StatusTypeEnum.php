<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity\Enum;

enum StatusTypeEnum: string
{
	case Start = 'Start';
	case Normal = 'Normal';
	case Finish = 'Finish';
}
