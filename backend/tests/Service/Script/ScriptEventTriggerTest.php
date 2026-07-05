<?php

declare(strict_types=1);

namespace Kytario\Tests\Service\Script;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;
use Kytario\Model\Entity\Enum\EventTypeEnum;
use Kytario\Model\Entity\Enum\ScriptTriggerEnum;
use Kytario\Model\Entity\Event;
use Kytario\Model\Entity\Script;
use Kytario\Model\Repository\ScriptRepository;
use Kytario\Service\Script\ScriptExecutionGuard;
use Kytario\Service\Script\ScriptProviderInterface;
use Kytario\Service\Script\ScriptRunDispatcherInterface;
use Kytario\Service\Script\Trigger\ScriptEventTrigger;
use Kytario\Tests\Support\AppHarness;
use Kytario\Tests\Support\Fixture;
use Kytario\Tests\Support\IntegrationTestCase;

#[CoversClass(ScriptEventTrigger::class)]
final class ScriptEventTriggerTest extends IntegrationTestCase
{
	public function testDispatchesSubscribedScriptAndRespectsLoopGuard(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);

		$provider = AppHarness::container()->get(ScriptProviderInterface::class);
		assert($provider instanceof ScriptProviderInterface);
		$script = $provider->create(
			$user,
			$workspace,
			'On task created',
			'kytario.log("hi");',
			ScriptTriggerEnum::Event,
			'["TaskCreated"]',
			true,
		);

		$dispatcher = new class implements ScriptRunDispatcherInterface {
			/** @var list<int> */
			public array $calls = [];

			/** @param array<string, mixed>|null $event */
			public function dispatch(
				Script $script,
				ScriptTriggerEnum $triggerType,
				?array $event = null,
				?string $scheduledAt = null,
			): void
			{
				$this->calls[] = $script->id;
			}
		};

		$repository = AppHarness::container()->get(ScriptRepository::class);
		assert($repository instanceof ScriptRepository);
		$trigger = new ScriptEventTrigger($repository, $dispatcher, new NullLogger());

		$event = new Event(author: $user, type: EventTypeEnum::TaskCreated, metadata: '{}', workspaceId: $workspace->id);
		$event->id = 1;

		$trigger->onEvent($event);
		self::assertSame([$script->id], $dispatcher->calls, 'subscribed script should be dispatched');

		// Loop guard: events recorded while a script is running must not re-dispatch.
		ScriptExecutionGuard::enter();

		try {
			$trigger->onEvent($event);
		} finally {
			ScriptExecutionGuard::leave();
		}

		self::assertSame([$script->id], $dispatcher->calls, 'no further dispatch while the execution guard is active');
	}
}
