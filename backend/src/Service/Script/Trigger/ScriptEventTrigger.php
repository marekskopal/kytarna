<?php

declare(strict_types=1);

namespace Ukolio\Service\Script\Trigger;

use Psr\Log\LoggerInterface;
use Throwable;
use Ukolio\Model\Entity\Enum\ScriptTriggerEnum;
use Ukolio\Model\Entity\Event;
use Ukolio\Model\Entity\Script;
use Ukolio\Model\Repository\ScriptRepository;
use Ukolio\Service\Script\ScriptExecutionGuard;
use Ukolio\Service\Script\ScriptRunDispatcherInterface;

final readonly class ScriptEventTrigger implements ScriptEventTriggerInterface
{
	public function __construct(
		private ScriptRepository $scriptRepository,
		private ScriptRunDispatcherInterface $dispatcher,
		private LoggerInterface $logger,
	) {
	}

	public function onEvent(Event $event): void
	{
		// Loop guard: events recorded by a running script must not enqueue further runs.
		if (ScriptExecutionGuard::isActive()) {
			return;
		}

		$workspaceId = $event->workspaceId;
		if ($workspaceId === null) {
			return;
		}

		$eventType = $event->type->value;

		try {
			foreach ($this->scriptRepository->findActiveByWorkspaceAndTrigger($workspaceId, ScriptTriggerEnum::Event) as $script) {
				if ($this->subscribesTo($script, $eventType)) {
					$this->dispatcher->dispatch($script, ScriptTriggerEnum::Event, $this->payload($event));
				}
			}
		} catch (Throwable $e) {
			// Trigger dispatch is best-effort; it must never break the mutation that recorded the event.
			$this->logger->error('Script event-trigger dispatch failed: ' . $e->getMessage(), ['exception' => $e]);
		}
	}

	private function subscribesTo(Script $script, string $eventType): bool
	{
		if ($script->triggerConfig === null) {
			return false;
		}

		$decoded = json_decode($script->triggerConfig, true);

		return is_array($decoded) && in_array($eventType, $decoded, true);
	}

	/** @return array<string, mixed> */
	private function payload(Event $event): array
	{
		return [
			'eventId' => $event->id,
			'type' => $event->type->value,
			'projectId' => $event->project?->id,
			'taskId' => $event->taskId,
		];
	}
}
