<?php

declare(strict_types=1);

namespace Kytario\Jobs\Handler;

use Psr\Log\LoggerInterface;
use Kytario\Dto\ScriptRunQueueDto;
use Kytario\Jobs\Message\ReceivedMessageInterface;
use Kytario\Model\Entity\Enum\ScriptTriggerEnum;
use Kytario\Model\Repository\ScriptRepository;
use Kytario\Service\Script\ScriptRunner;
use Kytario\Service\Task\TaskServiceInterface;

final readonly class ScriptRunHandler implements JobHandler
{
	public function __construct(
		private LoggerInterface $logger,
		private TaskServiceInterface $taskService,
		private ScriptRepository $scriptRepository,
		private ScriptRunner $runner,
	) {
	}

	public function handle(ReceivedMessageInterface $message): void
	{
		$payload = $this->taskService->getPayloadDto($message, ScriptRunQueueDto::class);

		$script = $this->scriptRepository->findById($payload->scriptId);
		if ($script === null) {
			$this->logger->warning('Script run skipped: script not found', ['scriptId' => $payload->scriptId]);

			return;
		}

		if (!$script->active) {
			$this->logger->info('Script run skipped: script inactive', ['scriptId' => $payload->scriptId]);

			return;
		}

		$trigger = ScriptTriggerEnum::tryFrom($payload->triggerType) ?? ScriptTriggerEnum::Manual;
		$this->runner->run($script, $trigger, $payload->event, $payload->scheduledAt);
	}
}
