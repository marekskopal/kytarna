<?php

declare(strict_types=1);

namespace Ukolio\Service\Script;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Throwable;
use Ukolio\Model\Entity\Enum\EventTypeEnum;
use Ukolio\Model\Entity\Enum\ScriptRunStatusEnum;
use Ukolio\Model\Entity\Enum\ScriptTriggerEnum;
use Ukolio\Model\Entity\Script;
use Ukolio\Model\Entity\ScriptRun;
use Ukolio\Model\Repository\ScriptRepository;
use Ukolio\Model\Repository\ScriptRunRepository;
use Ukolio\Service\Provider\EventProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Script\Engine\ScriptEngineInterface;
use Ukolio\Service\Script\Host\ScriptHostApiFactory;
use Ukolio\Service\Script\Host\ScriptRunContext;

/**
 * Executes a Script in the sandbox and records a ScriptRun. Always runs in a worker (never in an
 * HTTP request). Every run produces a ScriptRun row regardless of outcome.
 */
final readonly class ScriptRunner
{
	private const int TimeLimitMs = 5000;
	private const int MemoryLimitBytes = 67108864;
	private const int MaxHttpCalls = 20;
	private const int MaxTaskApiCalls = 200;

	/** Reserved workspace variable holding an optional comma/whitespace-separated outbound-fetch host allowlist. */
	private const string FetchAllowlistKey = 'UKOLIO_FETCH_ALLOWLIST';

	public function __construct(
		private ScriptEngineInterface $engine,
		private ScriptHostApiFactory $hostApiFactory,
		private ScriptRunRepository $scriptRunRepository,
		private ScriptRepository $scriptRepository,
		private ScriptVariableProviderInterface $variableProvider,
		private EventProviderInterface $eventProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private LoggerInterface $logger,
	) {
	}

	/** @param array<string, mixed>|null $event */
	public function run(Script $script, ScriptTriggerEnum $triggerType, ?array $event = null, ?string $scheduledAt = null): ScriptRun
	{
		$startedAt = new DateTimeImmutable();
		$run = new ScriptRun(script: $script, triggerType: $triggerType, status: ScriptRunStatusEnum::Running, startedAt: $startedAt);
		$run->createdAt = $startedAt;
		$run->updatedAt = $startedAt;
		$this->scriptRunRepository->persist($run);

		$context = new ScriptRunContext(
			owner: $script->createdBy,
			workspace: $script->workspace,
			triggerType: $triggerType,
			event: $event,
			scheduledAt: $scheduledAt,
			maxHttpCalls: self::MaxHttpCalls,
			maxTaskApiCalls: self::MaxTaskApiCalls,
			allowedFetchHosts: $this->resolveFetchAllowlist($script),
		);

		ScriptExecutionGuard::enter();

		try {
			// The script runs with its creator's identity for every host mutation. If that user has
			// been removed from the workspace, refuse to execute rather than act with stale privileges.
			if (!$this->workspaceProvider->isMember($script->createdBy, $script->workspace)) {
				$run->status = ScriptRunStatusEnum::Error;
				$run->error = 'Script owner is no longer a member of the workspace; run skipped.';
			} else {
				$hostApi = $this->hostApiFactory->create($context);
				$result = $this->engine->execute($script->source, $hostApi, self::TimeLimitMs, self::MemoryLimitBytes);
				$run->status = $result->status;
				$run->error = $result->error;
			}
		} catch (Throwable $e) {
			// Defensive: a host/engine fault must still close out the run row, not crash the worker.
			$this->logger->error('Script run crashed: ' . $e->getMessage(), ['scriptId' => $script->id, 'exception' => $e]);
			$run->status = ScriptRunStatusEnum::Error;
			$run->error = $e->getMessage();
		} finally {
			ScriptExecutionGuard::leave();
		}

		// A secret interpolated into a thrown error (e.g. into a fetch URL) must not survive into the
		// run history, which is rendered verbatim by the API. Scrub it like the captured logs.
		if ($run->error !== null) {
			$run->error = $context->redactSecrets($run->error);
		}

		$finishedAt = new DateTimeImmutable();
		$run->logs = $context->getLogs();
		$run->httpCalls = $context->getHttpCalls();
		$run->taskApiCalls = $context->getTaskApiCalls();
		$run->finishedAt = $finishedAt;
		$run->updatedAt = $finishedAt;
		$this->scriptRunRepository->persist($run);

		$script->lastRunAt = $finishedAt;
		$script->updatedAt = $finishedAt;
		$this->scriptRepository->persist($script);

		// Audit the run on the workspace event log. Recorded after the execution guard is
		// released; ScriptRun is not a subscribable trigger type, so it cannot re-dispatch.
		$this->eventProvider->recordWorkspaceEvent($script->createdBy, $script->workspace, EventTypeEnum::ScriptRun, [
			'scriptId' => $script->id,
			'scriptName' => $script->name,
			'runId' => $run->id,
			'triggerType' => $triggerType->value,
			'status' => $run->status->value,
		]);

		return $run;
	}

	/** @return list<string> lowercase host patterns; empty means "no restriction". */
	private function resolveFetchAllowlist(Script $script): array
	{
		$variable = $this->variableProvider->get($script->workspace, self::FetchAllowlistKey);
		if ($variable === null) {
			return [];
		}

		$parts = preg_split('/[\s,]+/', strtolower(trim($this->variableProvider->decrypt($variable))));
		$hosts = $parts === false ? [] : $parts;

		return array_values(array_filter($hosts, static fn (string $h): bool => $h !== ''));
	}
}
