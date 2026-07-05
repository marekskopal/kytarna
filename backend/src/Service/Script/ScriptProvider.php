<?php

declare(strict_types=1);

namespace Kytario\Service\Script;

use DateTimeImmutable;
use Iterator;
use RuntimeException;
use Kytario\Model\Entity\Enum\EventTypeEnum;
use Kytario\Model\Entity\Enum\ScriptTriggerEnum;
use Kytario\Model\Entity\Script;
use Kytario\Model\Entity\ScriptRun;
use Kytario\Model\Entity\User;
use Kytario\Model\Entity\Workspace;
use Kytario\Model\Repository\ScriptRepository;
use Kytario\Model\Repository\ScriptRunRepository;
use Kytario\Service\Provider\EventProviderInterface;
use Kytario\Service\Script\Trigger\CronEvaluatorInterface;

final readonly class ScriptProvider implements ScriptProviderInterface
{
	private const int MaxNameLength = 120;
	private const int MaxSourceLength = 102400;
	private const int MaxScriptsPerWorkspace = 50;
	private const int MaxScheduledPerWorkspace = 20;

	public function __construct(
		private ScriptRepository $scriptRepository,
		private ScriptRunRepository $scriptRunRepository,
		private CronEvaluatorInterface $cronEvaluator,
		private EventProviderInterface $eventProvider,
	) {
	}

	/** @return Iterator<Script> */
	public function listForWorkspace(Workspace $workspace): Iterator
	{
		return $this->scriptRepository->findByWorkspace($workspace->id);
	}

	public function get(Workspace $workspace, int $scriptId): ?Script
	{
		return $this->scriptRepository->findOneByWorkspaceAndId($workspace->id, $scriptId);
	}

	public function getScript(int $scriptId): ?Script
	{
		return $this->scriptRepository->findById($scriptId);
	}

	public function create(
		User $author,
		Workspace $workspace,
		string $name,
		string $source,
		ScriptTriggerEnum $trigger,
		?string $triggerConfig,
		bool $active,
	): Script {
		$name = $this->validateName($workspace, $name, null);
		$this->validateSource($source);
		$triggerConfig = $this->validateTriggerConfig($trigger, $triggerConfig);

		if ($this->scriptRepository->countByWorkspace($workspace->id) >= self::MaxScriptsPerWorkspace) {
			throw new RuntimeException(sprintf('Workspace script limit reached (max %d).', self::MaxScriptsPerWorkspace));
		}
		$this->assertScheduledCapacity($workspace, $trigger, null);

		$now = new DateTimeImmutable();
		$script = new Script(
			workspace: $workspace,
			createdBy: $author,
			name: $name,
			source: $source,
			trigger: $trigger,
			triggerConfig: $triggerConfig,
			active: $active,
		);
		$script->createdAt = $now;
		$script->updatedAt = $now;
		$this->scriptRepository->persist($script);

		$this->eventProvider->recordWorkspaceEvent($author, $workspace, EventTypeEnum::ScriptCreated, [
			'scriptId' => $script->id,
			'scriptName' => $script->name,
			'trigger' => $trigger->value,
		]);

		return $script;
	}

	public function update(
		User $author,
		Script $script,
		string $name,
		string $source,
		ScriptTriggerEnum $trigger,
		?string $triggerConfig,
		bool $active,
	): Script {
		$script->name = $this->validateName($script->workspace, $name, $script->id);
		$this->validateSource($source);
		$this->assertScheduledCapacity($script->workspace, $trigger, $script->id);

		$script->source = $source;
		$script->trigger = $trigger;
		$script->triggerConfig = $this->validateTriggerConfig($trigger, $triggerConfig);
		$script->active = $active;
		$script->updatedAt = new DateTimeImmutable();
		$this->scriptRepository->persist($script);

		$this->eventProvider->recordWorkspaceEvent($author, $script->workspace, EventTypeEnum::ScriptUpdated, [
			'scriptId' => $script->id,
			'scriptName' => $script->name,
			'trigger' => $trigger->value,
		]);

		return $script;
	}

	public function delete(User $author, Script $script): void
	{
		$workspace = $script->workspace;
		$metadata = ['scriptId' => $script->id, 'scriptName' => $script->name];

		$this->scriptRepository->delete($script);

		$this->eventProvider->recordWorkspaceEvent($author, $workspace, EventTypeEnum::ScriptDeleted, $metadata);
	}

	/** @return Iterator<ScriptRun> */
	public function runHistory(Script $script, int $limit, int $offset): Iterator
	{
		return $this->scriptRunRepository->findByScript($script->id, $limit, $offset);
	}

	public function runCount(Script $script): int
	{
		return $this->scriptRunRepository->countByScript($script->id);
	}

	public function lastStatus(Script $script): ?string
	{
		return $this->scriptRunRepository->findLatestByScript($script->id)?->status->value;
	}

	private function validateName(Workspace $workspace, string $name, ?int $ignoreId): string
	{
		$name = trim($name);
		if ($name === '' || mb_strlen($name) > self::MaxNameLength) {
			throw new RuntimeException(sprintf('Script name must be 1-%d characters.', self::MaxNameLength));
		}

		$existing = $this->scriptRepository->findOneByWorkspaceAndName($workspace->id, $name);
		if ($existing !== null && $existing->id !== $ignoreId) {
			throw new RuntimeException(sprintf('A script named "%s" already exists in this workspace.', $name));
		}

		return $name;
	}

	private function validateSource(string $source): void
	{
		if (strlen($source) > self::MaxSourceLength) {
			throw new RuntimeException(sprintf('Script source must be at most %d bytes.', self::MaxSourceLength));
		}
	}

	private function validateTriggerConfig(ScriptTriggerEnum $trigger, ?string $triggerConfig): ?string
	{
		$triggerConfig = $triggerConfig !== null ? trim($triggerConfig) : null;

		if ($trigger === ScriptTriggerEnum::Manual) {
			return null;
		}

		if ($triggerConfig === null || $triggerConfig === '') {
			throw new RuntimeException(sprintf('Trigger "%s" requires a configuration (cron expression or event types).', $trigger->value));
		}

		if ($trigger === ScriptTriggerEnum::Scheduled && !$this->cronEvaluator->isValid($triggerConfig)) {
			throw new RuntimeException('Scheduled trigger requires a valid cron expression (e.g. "0 9 * * 1").');
		}

		if ($trigger === ScriptTriggerEnum::Event && !$this->isValidEventConfig($triggerConfig)) {
			throw new RuntimeException('Event trigger requires a non-empty JSON array of event type names.');
		}

		return $triggerConfig;
	}

	private function isValidEventConfig(string $triggerConfig): bool
	{
		$decoded = json_decode($triggerConfig, true);
		if (!is_array($decoded) || $decoded === []) {
			return false;
		}

		foreach ($decoded as $eventType) {
			if (!is_string($eventType) || $eventType === '') {
				return false;
			}
		}

		return true;
	}

	private function assertScheduledCapacity(Workspace $workspace, ScriptTriggerEnum $trigger, ?int $ignoreId): void
	{
		if ($trigger !== ScriptTriggerEnum::Scheduled) {
			return;
		}

		$count = $this->scriptRepository->countByWorkspaceAndTrigger($workspace->id, ScriptTriggerEnum::Scheduled);
		$existing = $ignoreId !== null ? $this->scriptRepository->findOneByWorkspaceAndId($workspace->id, $ignoreId) : null;
		// Subtract the script being updated if it is already a scheduled one (it occupies a slot already).
		if ($existing !== null && $existing->trigger === ScriptTriggerEnum::Scheduled) {
			$count--;
		}

		if ($count >= self::MaxScheduledPerWorkspace) {
			throw new RuntimeException(sprintf('Workspace scheduled-script limit reached (max %d).', self::MaxScheduledPerWorkspace));
		}
	}
}
