<?php

declare(strict_types=1);

namespace Kytario\Model\Repository\Enum;

enum OrderDirectionEnum: string
{
	case Asc = 'ASC';
	case Desc = 'DESC';
}
