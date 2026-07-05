<?php

declare(strict_types=1);

namespace Ukolio\Dto;

/** @implements ArrayFactoryInterface<array{scriptId: int, triggerType: string, event?: array<string, mixed>|null, scheduledAt?: string|null}> */
final readonly class ScriptRunQueueDto implements ArrayFactoryInterface
{
	/** @param array<string, mixed>|null $event */
	public function __construct(
		public int $scriptId,
		public string $triggerType,
		public ?array $event = null,
		public ?string $scheduledAt = null,
	) {
	}

	public static function fromArray(array $data): static
	{
		return new self(
			scriptId: $data['scriptId'],
			triggerType: $data['triggerType'],
			event: $data['event'] ?? null,
			scheduledAt: $data['scheduledAt'] ?? null,
		);
	}
}
