<?php

declare(strict_types=1);

namespace Ukolio\Service\Script\Engine;

interface ScriptEngineInterface
{
	/**
	 * Execute user JavaScript in a fresh, isolated sandbox.
	 *
	 * @param object $hostApi the host object graph exposed to the script as the global `ukolio`
	 * @param int $timeLimitMs wall-clock limit in milliseconds (0 = unlimited)
	 * @param int $memoryLimitBytes heap limit in bytes (0 = unlimited)
	 */
	public function execute(string $source, object $hostApi, int $timeLimitMs, int $memoryLimitBytes): ScriptExecutionResult;

	/** Whether the underlying engine is available in this runtime (ext-v8js loaded). */
	public function isAvailable(): bool;
}
