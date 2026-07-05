<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity\Enum;

enum ScriptRunStatusEnum: string
{
	case Running = 'Running';
	case Success = 'Success';
	case Error = 'Error';
	case Timeout = 'Timeout';
}
