<?php

declare(strict_types=1);

namespace Ukolio\Service\Script;

use Iterator;
use Ukolio\Model\Entity\Enum\ScriptTriggerEnum;
use Ukolio\Model\Entity\Script;
use Ukolio\Model\Entity\ScriptRun;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;

interface ScriptProviderInterface
{
	/** @return Iterator<Script> */
	public function listForWorkspace(Workspace $workspace): Iterator;

	public function get(Workspace $workspace, int $scriptId): ?Script;

	/** Load a script by id without a workspace context; the caller must authorise against its workspace. */
	public function getScript(int $scriptId): ?Script;

	public function create(
		User $author,
		Workspace $workspace,
		string $name,
		string $source,
		ScriptTriggerEnum $trigger,
		?string $triggerConfig,
		bool $active,
	): Script;

	public function update(
		User $author,
		Script $script,
		string $name,
		string $source,
		ScriptTriggerEnum $trigger,
		?string $triggerConfig,
		bool $active,
	): Script;

	public function delete(User $author, Script $script): void;

	/** @return Iterator<ScriptRun> */
	public function runHistory(Script $script, int $limit, int $offset): Iterator;

	public function runCount(Script $script): int;

	/** Status value (Running/Success/Error/Timeout) of the script's most recent run, or null if it never ran. */
	public function lastStatus(Script $script): ?string;
}
