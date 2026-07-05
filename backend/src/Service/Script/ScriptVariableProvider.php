<?php

declare(strict_types=1);

namespace Ukolio\Service\Script;

use DateTimeImmutable;
use Iterator;
use RuntimeException;
use Ukolio\Model\Entity\ScriptVariable;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\ScriptVariableRepository;

final readonly class ScriptVariableProvider implements ScriptVariableProviderInterface
{
	private const int MaxKeyLength = 128;
	private const int MaxValueLength = 16384;

	public function __construct(private ScriptVariableRepository $scriptVariableRepository, private SecretCipherInterface $secretCipher,)
	{
	}

	/** @return Iterator<ScriptVariable> */
	public function listForWorkspace(Workspace $workspace): Iterator
	{
		return $this->scriptVariableRepository->findByWorkspace($workspace->id);
	}

	public function get(Workspace $workspace, string $key): ?ScriptVariable
	{
		return $this->scriptVariableRepository->findOneByWorkspaceAndKey($workspace->id, $key);
	}

	public function getById(Workspace $workspace, int $variableId): ?ScriptVariable
	{
		return $this->scriptVariableRepository->findOneByWorkspaceAndId($workspace->id, $variableId);
	}

	public function decrypt(ScriptVariable $variable): string
	{
		return $variable->isSecret ? $this->secretCipher->decrypt($variable->value) : $variable->value;
	}

	public function set(Workspace $workspace, string $key, string $value, bool $isSecret): ScriptVariable
	{
		$key = trim($key);
		if ($key === '' || strlen($key) > self::MaxKeyLength) {
			throw new RuntimeException(sprintf('Variable key must be 1-%d characters.', self::MaxKeyLength));
		}
		if (strlen($value) > self::MaxValueLength) {
			throw new RuntimeException(sprintf('Variable value must be at most %d bytes.', self::MaxValueLength));
		}

		$stored = $isSecret ? $this->secretCipher->encrypt($value) : $value;

		$now = new DateTimeImmutable();
		$variable = $this->scriptVariableRepository->findOneByWorkspaceAndKey($workspace->id, $key);
		if ($variable === null) {
			$variable = new ScriptVariable(workspace: $workspace, key: $key, value: $stored, isSecret: $isSecret);
			$variable->createdAt = $now;
		} else {
			$variable->value = $stored;
			$variable->isSecret = $isSecret;
		}
		$variable->updatedAt = $now;
		$this->scriptVariableRepository->persist($variable);

		return $variable;
	}

	public function delete(ScriptVariable $variable): void
	{
		$this->scriptVariableRepository->delete($variable);
	}
}
