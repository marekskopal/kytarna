<?php

declare(strict_types=1);

namespace Kytarna\Model\Entity\Enum;

/**
 * Fixed learning status for lectures and songs. Declaration order is significant:
 * the MySQL ENUM column sorts by declaration index, so `ORDER BY status` yields
 * To Learn -> Learning -> Mastered. `Mastered` is the terminal state (an item is
 * "active" while it is not yet Mastered).
 */
enum LearningStatusEnum: string
{
	case ToLearn = 'ToLearn';
	case Learning = 'Learning';
	case Mastered = 'Mastered';

	public function label(): string
	{
		return match ($this) {
			self::ToLearn => 'To Learn',
			self::Learning => 'Learning',
			self::Mastered => 'Mastered',
		};
	}

	/** Resolve from an enum value ("ToLearn") or a human label ("To Learn"), case-insensitively. */
	public static function fromLoose(string $raw): ?self
	{
		$exact = self::tryFrom($raw);
		if ($exact !== null) {
			return $exact;
		}
		$needle = mb_strtolower(trim($raw));
		foreach (self::cases() as $case) {
			if (mb_strtolower($case->value) === $needle || mb_strtolower($case->label()) === $needle) {
				return $case;
			}
		}
		return null;
	}
}
