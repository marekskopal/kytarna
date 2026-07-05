<?php

declare(strict_types=1);

namespace Kytario\Model\Entity\Enum;

enum TabSourceTypeEnum: string
{
	case Authored = 'authored';
	case ImportedGp = 'imported_gp';
}
