<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use RuntimeException;
use Ukolio\Dto\ScriptDto;
use Ukolio\Dto\ScriptRunDto;
use Ukolio\Mcp\Dto\McpScriptListDto;
use Ukolio\Mcp\Dto\McpScriptRunListDto;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Model\Entity\Enum\ScriptTriggerEnum;
use Ukolio\Model\Entity\Script;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Service\Auth\PermissionCheckerInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Script\ScriptProviderInterface;
use Ukolio\Service\Script\ScriptRunDispatcherInterface;

/**
 * Manage sandboxed automation Scripts (the `ukolio.*` JS runtime). Mutations require workspace
 * admin (canManageScripts). Scheduled triggers use a standard 5-field cron in triggerConfig
 * (e.g. "0 3 * * *"); Event triggers use a JSON array of event-type names.
 */
final readonly class ScriptTools
{
	private const int DefaultRunsLimit = 25;
	private const int MaxRunsLimit = 100;

	public function __construct(
		private McpUserContextInterface $userContext,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
		private ScriptProviderInterface $scriptProvider,
		private ScriptRunDispatcherInterface $scriptRunDispatcher,
	) {
	}

	/** List all automation scripts in the current workspace. */
	#[McpTool(name: 'list_scripts', description: 'List automation scripts in the current workspace')]
	public function listScripts(): McpScriptListDto
	{
		$workspace = $this->requireWorkspace();
		$this->requireView($workspace);

		$scripts = [];
		foreach ($this->scriptProvider->listForWorkspace($workspace) as $script) {
			$scripts[] = $this->toDto($script);
		}

		return new McpScriptListDto($scripts);
	}

	/**
	 * Get a single script (including its source) by id.
	 *
	 * @param int $scriptId Script id
	 */
	#[McpTool(name: 'get_script', description: 'Get a single automation script by id, including its source.')]
	public function getScript(int $scriptId): ScriptDto
	{
		$workspace = $this->requireWorkspace();
		$this->requireView($workspace);

		return $this->toDto($this->require($workspace, $scriptId));
	}

	/**
	 * Create an automation script. Requires workspace admin.
	 *
	 * @param string $name Display name
	 * @param string $source JavaScript source using the `ukolio` API
	 * @param string $trigger One of "Manual", "Scheduled", "Event" (default "Manual")
	 * @param string|null $triggerConfig Cron expression for Scheduled (e.g. "0 3 * * *"); JSON array of event types for Event; null for Manual
	 * @param bool $active Whether the script is enabled (default true)
	 */
	#[McpTool(
		name: 'create_script',
		description: 'Create a sandboxed automation script. Scheduled triggers take a 5-field cron in triggerConfig. Requires workspace admin.',
	)]
	public function createScript(
		string $name,
		string $source,
		string $trigger = 'Manual',
		?string $triggerConfig = null,
		bool $active = true,
	): ScriptDto {
		$workspace = $this->requireWorkspace();
		$this->requireManage($workspace);

		$script = $this->scriptProvider->create(
			$this->userContext->getUser(),
			$workspace,
			$name,
			$source,
			$this->resolveTrigger($trigger),
			$triggerConfig,
			$active,
		);

		return $this->toDto($script);
	}

	/**
	 * Update a script. Omitted fields keep their current value. Requires workspace admin.
	 *
	 * @param int $scriptId Script id
	 * @param string|null $name New name (keeps current if omitted)
	 * @param string|null $source New JavaScript source (keeps current if omitted)
	 * @param string|null $trigger New trigger "Manual"|"Scheduled"|"Event" (keeps current if omitted)
	 * @param string|null $triggerConfig New cron / event-type config (keeps current if omitted)
	 * @param bool|null $active Enable/disable (keeps current if omitted)
	 */
	#[McpTool(
		name: 'update_script',
		description: 'Update an automation script. Omitted fields are left unchanged. Requires workspace admin.',
	)]
	public function updateScript(
		int $scriptId,
		?string $name = null,
		?string $source = null,
		?string $trigger = null,
		?string $triggerConfig = null,
		?bool $active = null,
	): ScriptDto {
		$workspace = $this->requireWorkspace();
		$this->requireManage($workspace);
		$script = $this->require($workspace, $scriptId);

		$updated = $this->scriptProvider->update(
			$this->userContext->getUser(),
			$script,
			$name ?? $script->name,
			$source ?? $script->source,
			$trigger !== null ? $this->resolveTrigger($trigger) : $script->trigger,
			$triggerConfig ?? $script->triggerConfig,
			$active ?? $script->active,
		);

		return $this->toDto($updated);
	}

	/**
	 * Delete a script. Requires workspace admin.
	 *
	 * @param int $scriptId Script id
	 */
	#[McpTool(name: 'delete_script', description: 'Delete an automation script. Requires workspace admin.')]
	public function deleteScript(int $scriptId): string
	{
		$workspace = $this->requireWorkspace();
		$this->requireManage($workspace);

		$this->scriptProvider->delete($this->userContext->getUser(), $this->require($workspace, $scriptId));

		return 'Script deleted.';
	}

	/**
	 * Queue a script for an immediate one-off run (asynchronous). Poll list_script_runs for the
	 * result. Requires workspace admin.
	 *
	 * @param int $scriptId Script id
	 */
	#[McpTool(
		name: 'run_script',
		description: 'Queue a script for an immediate one-off run (async). Poll list_script_runs for the result.',
	)]
	public function runScript(int $scriptId): string
	{
		$workspace = $this->requireWorkspace();
		$this->requireManage($workspace);
		$script = $this->require($workspace, $scriptId);

		$this->scriptRunDispatcher->dispatch($script, ScriptTriggerEnum::Manual);

		return sprintf('Queued script #%d ("%s") for execution.', $script->id, $script->name);
	}

	/**
	 * List run history for a script (newest first), including captured logs and errors.
	 *
	 * @param int $scriptId Script id
	 * @param int $limit Max runs to return (default 25, max 100)
	 * @param int $offset Pagination offset
	 */
	#[McpTool(name: 'list_script_runs', description: 'List a script\'s run history (newest first) with logs, status, and errors.')]
	public function listScriptRuns(int $scriptId, int $limit = self::DefaultRunsLimit, int $offset = 0): McpScriptRunListDto
	{
		$workspace = $this->requireWorkspace();
		// Run logs/errors can contain secret-variable values — restrict to script managers.
		$this->requireManage($workspace);
		$script = $this->require($workspace, $scriptId);

		$boundedLimit = min(max($limit, 1), self::MaxRunsLimit);
		$boundedOffset = max($offset, 0);

		$runs = [];
		foreach ($this->scriptProvider->runHistory($script, $boundedLimit, $boundedOffset) as $run) {
			$runs[] = ScriptRunDto::fromEntity($run);
		}

		return new McpScriptRunListDto($runs);
	}

	private function toDto(Script $script): ScriptDto
	{
		return ScriptDto::fromEntityWithStats(
			$script,
			$this->scriptProvider->lastStatus($script),
			$this->scriptProvider->runCount($script),
		);
	}

	private function resolveTrigger(string $trigger): ScriptTriggerEnum
	{
		return ScriptTriggerEnum::tryFrom($trigger)
			?? throw new RuntimeException(sprintf('Invalid trigger "%s". Expected Manual, Scheduled, or Event.', $trigger));
	}

	private function require(Workspace $workspace, int $scriptId): Script
	{
		return $this->scriptProvider->get($workspace, $scriptId)
			?? throw new RuntimeException(sprintf('Script %d not found.', $scriptId));
	}

	private function requireView(Workspace $workspace): void
	{
		if (!$this->permissionChecker->canViewWorkspace($this->userContext->getUser(), $workspace)) {
			throw new RuntimeException('You do not have access to this workspace.');
		}
	}

	private function requireManage(Workspace $workspace): void
	{
		if (!$this->permissionChecker->canManageScripts($this->userContext->getUser(), $workspace)) {
			throw new RuntimeException('Only workspace admins can manage scripts.');
		}
	}

	private function requireWorkspace(): Workspace
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->userContext->getUser());
		if ($workspace === null) {
			throw new RuntimeException('No active workspace. Create one in the Ukolio app first.');
		}

		return $workspace;
	}
}
