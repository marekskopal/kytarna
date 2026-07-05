<?php

declare(strict_types=1);

namespace Kytario\Model\Entity\Enum;

enum LectureLinkKindEnum: string
{
	case Youtube = 'youtube';
	case Other = 'other';
}
