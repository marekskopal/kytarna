<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository\Enum;

enum SubtaskFilterEnum: string
{
	case All = 'all';
	case HideSubtasks = 'hideSubtasks';
	case OnlyParents = 'onlyParents';
}
