<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity\Enum;

enum TaskRelationTypeEnum: string
{
	case Related = 'Related';
	case Duplicates = 'Duplicates';
	case Parent = 'Parent';
	case DependsOn = 'DependsOn';

	public function isSymmetric(): bool
	{
		return match ($this) {
			self::Related, self::Duplicates => true,
			self::Parent, self::DependsOn => false,
		};
	}
}
