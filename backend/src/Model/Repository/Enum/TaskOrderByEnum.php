<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository\Enum;

enum TaskOrderByEnum: string
{
	case CreatedAt = 'created_at';
	case Name = 'name';
	case Status = 'status_id';
}
