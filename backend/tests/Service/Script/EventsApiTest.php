<?php

declare(strict_types=1);

namespace Kytario\Tests\Service\Script;

use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Kytario\Mcp\McpUserContextInterface;
use Kytario\Mcp\Tool\TaskTools;
use Kytario\Model\Entity\Enum\ScriptTriggerEnum;
use Kytario\Service\Actor\ActorContextInterface;
use Kytario\Service\Script\Host\EventsApi;
use Kytario\Service\Script\Host\ScriptHostApiFactory;
use Kytario\Service\Script\Host\ScriptRunContext;
use Kytario\Tests\Support\AppHarness;
use Kytario\Tests\Support\Fixture;
use Kytario\Tests\Support\IntegrationTestCase;

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
