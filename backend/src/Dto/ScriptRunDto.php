<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\ScriptRun;
use const DATE_ATOM;

final readonly class ScriptRunDto
{
	public function __construct(
		public int $id,
		public int $scriptId,
		public string $triggerType,
		public string $status,
		public ?string $startedAt,
		public ?string $finishedAt,
		public ?int $durationMs,
		public ?string $logs,
		public ?string $error,
		public int $httpCalls,
		public int $taskApiCalls,
		public string $createdAt,
	) {
	}

	public static function fromEntity(ScriptRun $run): self
	{
		$durationMs = null;
		if ($run->startedAt !== null && $run->finishedAt !== null) {
			$durationMs = (int) round(((float) $run->finishedAt->format('U.u') - (float) $run->startedAt->format('U.u')) * 1000);
		}

		return new self(
			id: $run->id,
			scriptId: $run->script->id,
			triggerType: $run->triggerType->value,
			status: $run->status->value,
			startedAt: $run->startedAt?->format(DATE_ATOM),
			finishedAt: $run->finishedAt?->format(DATE_ATOM),
			durationMs: $durationMs,
			logs: $run->logs,
			error: $run->error,
			httpCalls: $run->httpCalls,
			taskApiCalls: $run->taskApiCalls,
			createdAt: $run->createdAt->format(DATE_ATOM),
		);
	}
}
