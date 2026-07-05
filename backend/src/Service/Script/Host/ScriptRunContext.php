<?php

declare(strict_types=1);

namespace Kytario\Service\Script\Host;

use RuntimeException;
use Kytario\Model\Entity\Enum\ScriptTriggerEnum;
use Kytario\Model\Entity\User;
use Kytario\Model\Entity\Workspace;

/**
 * Mutable per-run state shared by every host-API object: the acting user/workspace, the captured
 * log, the secret-redaction set, and the call-count caps. One instance per ScriptRun.
 */
final class ScriptRunContext
{
	private const string Redaction = '••••••';

	/** @var list<string> */
	private array $logLines = [];

	/** @var list<string> non-empty secret plaintexts to scrub from captured logs */
	private array $secrets = [];

	private int $httpCalls = 0;

	private int $taskApiCalls = 0;

	/**
	 * @param array<string, mixed>|null $event
	 * @param list<string> $allowedFetchHosts lowercase host patterns; empty means no restriction
	 */
	public function __construct(
		public readonly User $owner,
		public readonly Workspace $workspace,
		public readonly ScriptTriggerEnum $triggerType,
		private readonly ?array $event = null,
		private readonly ?string $scheduledAt = null,
		private readonly int $maxHttpCalls = 20,
		private readonly int $maxTaskApiCalls = 200,
		private readonly array $allowedFetchHosts = [],
	) {
	}

	/** @return list<string> */
	public function getAllowedFetchHosts(): array
	{
		return $this->allowedFetchHosts;
	}

	public function log(string $line): void
	{
		$this->logLines[] = $line;
	}

	public function registerSecret(string $plaintext): void
	{
		if ($plaintext !== '') {
			$this->secrets[] = $plaintext;
		}
	}

	public function recordHttpCall(): void
	{
		$this->httpCalls++;
		if ($this->httpCalls > $this->maxHttpCalls) {
			throw new RuntimeException(sprintf('HTTP call limit exceeded (max %d per run).', $this->maxHttpCalls));
		}
	}

	public function recordTaskApiCall(): void
	{
		$this->taskApiCalls++;
		if ($this->taskApiCalls > $this->maxTaskApiCalls) {
			throw new RuntimeException(sprintf('Task API call limit exceeded (max %d per run).', $this->maxTaskApiCalls));
		}
	}

	/** @return array{triggerType: string, event: array<string, mixed>|null, scheduledAt: string|null} */
	public function contextArray(): array
	{
		return [
			'triggerType' => $this->triggerType->value,
			'event' => $this->event,
			'scheduledAt' => $this->scheduledAt,
		];
	}

	public function getHttpCalls(): int
	{
		return $this->httpCalls;
	}

	public function getTaskApiCalls(): int
	{
		return $this->taskApiCalls;
	}

	public function getLogs(): string
	{
		return $this->redactSecrets(implode("\n", $this->logLines));
	}

	/** Scrub every secret plaintext the run touched out of an arbitrary string (logs, error messages). */
	public function redactSecrets(string $text): string
	{
		return $this->redact($text);
	}

	private function redact(string $text): string
	{
		foreach ($this->secrets as $secret) {
			$text = str_replace($secret, self::Redaction, $text);
		}

		return $text;
	}
}
