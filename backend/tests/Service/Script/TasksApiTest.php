<?php

declare(strict_types=1);

namespace Ukolio\Tests\Service\Script;

use PHPUnit\Framework\Attributes\CoversClass;
use Ukolio\Model\Entity\Enum\ScriptTriggerEnum;
use Ukolio\Service\Script\Host\ScriptHostApiFactory;
use Ukolio\Service\Script\Host\ScriptRunContext;
use Ukolio\Service\Script\Host\TasksApi;
use Ukolio\Tests\Support\AppHarness;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(TasksApi::class)]
final class TasksApiTest extends IntegrationTestCase
{
	public function testScriptCannotReachTaskInAnotherWorkspaceOfItsOwner(): void
	{
		// The script owner belongs to both workspaces, but a script run is bound to ONE workspace.
		$owner = Fixture::createUser();
		$workspaceA = Fixture::createWorkspace($owner, 'WS A');
		$workspaceB = Fixture::createWorkspace($owner, 'WS B');
		$projectA = Fixture::createProject($owner, $workspaceA);
		$projectB = Fixture::createProject($owner, $workspaceB);

		$factory = AppHarness::container()->get(ScriptHostApiFactory::class);
		assert($factory instanceof ScriptHostApiFactory);

		$apiA = $factory->create(new ScriptRunContext($owner, $workspaceA, ScriptTriggerEnum::Manual));
		$apiB = $factory->create(new ScriptRunContext($owner, $workspaceB, ScriptTriggerEnum::Manual));

		$taskAId = self::intField($apiA->tasks->create(['projectId' => $projectA->id, 'name' => 'A task'])['id']);
		$taskBId = self::intField($apiB->tasks->create(['projectId' => $projectB->id, 'name' => 'B task'])['id']);

		// A run in workspace A can read its own task...
		self::assertNotNull($apiA->tasks->get($taskAId));
		// ...but must NOT reach a task in workspace B, even though the owner is a member there.
		self::assertNull($apiA->tasks->get($taskBId));
	}
}
