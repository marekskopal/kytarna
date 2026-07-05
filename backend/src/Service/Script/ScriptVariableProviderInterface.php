<?php

declare(strict_types=1);

namespace Ukolio\Service\Script;

use Iterator;
use Ukolio\Model\Entity\ScriptVariable;
use Ukolio\Model\Entity\Workspace;

interface ScriptVariableProviderInterface
{
	/** @return Iterator<ScriptVariable> */
	public function listForWorkspace(Workspace $workspace): Iterator;

	public function get(Workspace $workspace, string $key): ?ScriptVariable;

	public function getById(Workspace $workspace, int $variableId): ?ScriptVariable;

	/** Returns the plaintext value, decrypting transparently when the variable is a secret. */
	public function decrypt(ScriptVariable $variable): string;

	public function set(Workspace $workspace, string $key, string $value, bool $isSecret): ScriptVariable;

	public function delete(ScriptVariable $variable): void;
}
