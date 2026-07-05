<?php

declare(strict_types=1);

namespace Kytario\Service\Script;

use Kytario\Dto\ScriptRunQueueDto;
use Kytario\Model\Entity\Enum\ScriptTriggerEnum;
use Kytario\Model\Entity\Script;
use Kytario\Service\Queue\Enum\QueueEnum;
use Kytario\Service\Queue\QueuePublisher;

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
