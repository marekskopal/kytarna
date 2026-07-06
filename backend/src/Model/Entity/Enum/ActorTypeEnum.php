<?php

declare(strict_types=1);

namespace Kytarna\Model\Entity\Enum;

enum ActorTypeEnum: string
{
	case Human = 'Human';
	case Agent = 'Agent';
}
