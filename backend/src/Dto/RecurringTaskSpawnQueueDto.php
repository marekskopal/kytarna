<?php

declare(strict_types=1);

namespace Ukolio\Dto;

/**
 * Asks the worker to spawn the next occurrence of a recurrence. `expectedCarrierTaskId` is the task
 * that triggered the spawn; the handler skips the message if the series has since re-pointed to a
 * different carrier, so a completion + the safety tick can both enqueue but spawn exactly once.
 *
 * @implements ArrayFactoryInterface<array{recurrenceId: int, expectedCarrierTaskId: int}>
 */
final readonly class RecurringTaskSpawnQueueDto implements ArrayFactoryInterface
{
	public function __construct(public int $recurrenceId, public int $expectedCarrierTaskId,)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(recurrenceId: $data['recurrenceId'], expectedCarrierTaskId: $data['expectedCarrierTaskId']);
	}
}
