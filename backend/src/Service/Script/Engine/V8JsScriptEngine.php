<?php

declare(strict_types=1);

namespace Ukolio\Service\Script\Engine;

use Throwable;
use V8Js;
use V8JsMemoryLimitException;
use V8JsScriptException;
use V8JsTimeLimitException;

/**
 * Sandbox backed by Google V8 via ext-v8js. Each call spins up a fresh V8Js instance (isolate),
 * exposes the host API as the global `ukolio`, and runs the source under hard time/memory limits.
 *
 * The extension is only loaded in the dedicated script-worker container; everywhere else
 * isAvailable() returns false and execute() refuses to run.
 */
final readonly class V8JsScriptEngine implements ScriptEngineInterface
{
	// V8Js exposes properties assigned on the instance (`$v8->api = …`) under a single global object
	// named here. We expose the host API as `host.api` and alias it to `ukolio` so user code uses the
	// documented name. (This build's constructor is (object_name, variables, snapshot_blob) and does
	// not surface the `variables` map, so property assignment is the portable mechanism.)
	private const string GlobalObject = 'host';
	private const string Prelude = 'var ukolio = host.api;';

	public function isAvailable(): bool
	{
		return class_exists(V8Js::class);
	}

	public function execute(string $source, object $hostApi, int $timeLimitMs, int $memoryLimitBytes): ScriptExecutionResult
	{
		if (!$this->isAvailable()) {
			return ScriptExecutionResult::error('Sandbox unavailable: ext-v8js is not loaded in this runtime.');
		}

		// Uncaught JS errors propagate as V8JsScriptException by default in this build, so they are
		// recorded as failed runs rather than silently swallowed.
		$v8 = new V8Js(self::GlobalObject);
		$v8->api = $hostApi;
		$v8->setTimeLimit($timeLimitMs);
		$v8->setMemoryLimit($memoryLimitBytes);

		try {
			$v8->executeString(self::Prelude . "\n" . $source, 'script.js', V8Js::FLAG_NONE);

			return ScriptExecutionResult::success();
		} catch (V8JsTimeLimitException $e) {
			return ScriptExecutionResult::timeout('Script exceeded the time limit: ' . $e->getMessage());
		} catch (V8JsMemoryLimitException $e) {
			return ScriptExecutionResult::error('Script exceeded the memory limit: ' . $e->getMessage());
		} catch (V8JsScriptException $e) {
			return ScriptExecutionResult::error($this->formatScriptException($e));
		} catch (Throwable $e) {
			// Host-API exceptions (cap exceeded, not-found, etc.) surface here.
			return ScriptExecutionResult::error($e->getMessage());
		}
	}

	private function formatScriptException(V8JsScriptException $e): string
	{
		$line = $e->getJsLineNumber();
		$file = $e->getJsFileName();

		return sprintf('%s (%s:%d)', $e->getMessage(), $file === '' ? 'script.js' : $file, $line);
	}
}
