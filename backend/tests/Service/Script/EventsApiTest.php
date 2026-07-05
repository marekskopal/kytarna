<?php

declare(strict_types=1);

namespace Ukolio\Tests\Service\Script;

use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Mcp\Tool\TaskTools;
use Ukolio\Model\Entity\Enum\ScriptTriggerEnum;
use Ukolio\Service\Actor\ActorContextInterface;
use Ukolio\Service\Script\Host\EventsApi;
use Ukolio\Service\Script\Host\ScriptHostApiFactory;
use Ukolio\Service\Script\Host\ScriptRunContext;
use Ukolio\Tests\Support\AppHarness;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(EventsApi::class)]
final class EventsApiTest extends IntegrationTestCase
{
	public function testScriptCanReadTaskMoveEvents(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		// Generate events through the MCP task tools (create + move).
		$ctx = AppHarness::container()->get(McpUserContextInterface::class);
		assert($ctx instanceof McpUserContextInterface);
		$ctx->setUser($user);
		$actor = AppHarness::container()->get(ActorContextInterface::class);
		assert($actor instanceof ActorContextInterface);
		$actor->setAgent('cli', 'Test CLI');
		$taskTools = AppHarness::container()->get(TaskTools::class);
		assert($taskTools instanceof TaskTools);
		$task = $taskTools->createTask(projectId: $project->id, name: 'Done soon');
		$taskTools->moveTask(taskId: $task->id, statusName: 'Done');

		$factory = AppHarness::container()->get(ScriptHostApiFactory::class);
		assert($factory instanceof ScriptHostApiFactory);
		$api = $factory->create(new ScriptRunContext($user, $workspace, ScriptTriggerEnum::Scheduled));

		$moves = $api->events->list(['taskId' => $task->id, 'type' => 'TaskMoved']);
		self::assertCount(1, $moves);
		self::assertSame('TaskMoved', $moves[0]['type']);
		self::assertSame($task->id, $moves[0]['taskId']);
		$meta = $moves[0]['metadata'];
		self::assertIsArray($meta);
		self::assertSame('Done', $meta['toStatusName']);
		self::assertIsString($moves[0]['createdAt']);
	}

	public function testUnknownEventTypeThrows(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);

		$factory = AppHarness::container()->get(ScriptHostApiFactory::class);
		assert($factory instanceof ScriptHostApiFactory);
		$api = $factory->create(new ScriptRunContext($user, $workspace, ScriptTriggerEnum::Scheduled));

		$this->expectException(RuntimeException::class);
		$api->events->list(['type' => 'Nope']);
	}
}
