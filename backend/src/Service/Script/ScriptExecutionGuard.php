<?php

declare(strict_types=1);

namespace Ukolio\Service\Script;

/**
 * Process-global flag marking that the current worker is inside a script run. The event-driven
 * trigger dispatcher consults it so that events recorded by a script's own actions (e.g. creating
 * a task) do not recursively enqueue further script runs — preventing trigger loops.
 */
final class ScriptExecutionGuard
{
	private static bool $active = false;

	public static function enter(): void
	{
		self::$active = true;
	}

	public static function leave(): void
	{
		self::$active = false;
	}

	public static function isActive(): bool
	{
		return self::$active;
	}
}
