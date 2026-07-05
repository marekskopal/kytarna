<?php

declare(strict_types=1);

namespace Kytario\Model\Repository\Enum;

enum LectureOrderByEnum: string
{
	case CreatedAt = 'created_at';
	case Name = 'name';
	case Status = 'status_id';
}
