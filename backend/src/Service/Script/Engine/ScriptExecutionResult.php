<?php

declare(strict_types=1);

namespace Ukolio\Service\Script\Engine;

use Ukolio\Model\Entity\Enum\ScriptRunStatusEnum;

final readonly class ScriptExecutionResult
{
	public function __construct(public ScriptRunStatusEnum $status, public ?string $error = null,)
	{
	}

	public static function success(): self
	{
		return new self(ScriptRunStatusEnum::Success);
	}

	public static function error(string $message): self
	{
		return new self(ScriptRunStatusEnum::Error, $message);
	}

	public static function timeout(string $message): self
	{
		return new self(ScriptRunStatusEnum::Timeout, $message);
	}
}
