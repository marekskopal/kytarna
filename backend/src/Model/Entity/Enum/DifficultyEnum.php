<?php

declare(strict_types=1);

namespace Kytarna\Model\Entity\Enum;

enum DifficultyEnum: string
{
	case Beginner = 'Beginner';
	case Intermediate = 'Intermediate';
	case Advanced = 'Advanced';
}
