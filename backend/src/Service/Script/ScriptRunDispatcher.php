<?php

declare(strict_types=1);

namespace Ukolio\Service\Script;

use Ukolio\Dto\ScriptRunQueueDto;
use Ukolio\Model\Entity\Enum\ScriptTriggerEnum;
use Ukolio\Model\Entity\Script;
use Ukolio\Service\Queue\Enum\QueueEnum;
use Ukolio\Service\Queue\QueuePublisher;

final readonly class ScriptRunDispatcher implements ScriptRunDispatcherInterface
{
	public function __construct(private QueuePublisher $queuePublisher)
	{
	}

	/** @param array<string, mixed>|null $event */
	public function dispatch(Script $script, ScriptTriggerEnum $triggerType, ?array $event = null, ?string $scheduledAt = null): void
	{
		$this->queuePublisher->publishMessage(
			new ScriptRunQueueDto(
				scriptId: $script->id,
				triggerType: $triggerType->value,
				event: $event,
				scheduledAt: $scheduledAt,
			),
			QueueEnum::ScriptRun,
		);
	}
}
