<?php

declare(strict_types=1);

namespace Kytario\Tests\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Kytario\Mcp\McpUserContextInterface;
use Kytario\Mcp\Tool\EventTools;
use Kytario\Mcp\Tool\TaskTools;
use Kytario\Model\Entity\User;
use Kytario\Service\Actor\ActorContextInterface;
use Kytario\Tests\Support\AppHarness;
use Kytario\Tests\Support\Fixture;
use Kytario\Tests\Support\IntegrationTestCase;

#[CoversClass(EventTools::class)]
final class EventToolsTest extends IntegrationTestCase
{
	public function testListEventsReturnsWorkspaceEventsNewestFirst(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools, $eventTools] = $this->bootAs($user);

		$task = $taskTools->createTask(projectId: $project->id, name: 'Ship it');
		$taskTools->moveTask(taskId: $task->id, statusName: 'Done');

		$events = $eventTools->listEvents()->events;
		self::assertNotEmpty($events);
		// Newest first: the move is the most recent event.
		self::assertSame('TaskMoved', $events[0]->type);
		self::assertSame($task->id, $events[0]->taskId);
		$meta = $events[0]->metadata;
		self::assertIsArray($meta);
		self::assertSame('Done', $meta['toStatusName']);
	}

	public function testListEventsFiltersByType(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools, $eventTools] = $this->bootAs($user);

		$task = $taskTools->createTask(projectId: $project->id, name: 'Filter me');
		$taskTools->moveTask(taskId: $task->id, statusName: 'Done');

		$moved = $eventTools->listEvents(type: 'TaskMoved')->events;
		self::assertCount(1, $moved);
		self::assertSame('TaskMoved', $moved[0]->type);
		self::assertSame($task->id, $moved[0]->taskId);
		$meta = $moved[0]->metadata;
		self::assertIsArray($meta);
		self::assertSame('Done', $meta['toStatusName']);
	}

	public function testListTaskEventsScopesToSingleTaskByCode(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools, $eventTools] = $this->bootAs($user);

		$kept = $taskTools->createTask(projectId: $project->id, name: 'Keep');
		$other = $taskTools->createTask(projectId: $project->id, name: 'Other');
		$taskTools->moveTask(taskId: $other->id, statusName: 'Done');

		$events = $eventTools->listTaskEvents(taskId: $kept->code)->events;
		self::assertNotEmpty($events);
		foreach ($events as $event) {
			self::assertSame($kept->id, $event->taskId);
		}
	}

	public function testArchivingRecordsTaskArchivedEvent(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools, $eventTools] = $this->bootAs($user);

		$task = $taskTools->createTask(projectId: $project->id, name: 'Archive me');
		// Exercises the events.type ENUM accepting TaskArchived (see AddTaskArchivedEventTypes migration).
		$taskTools->archiveTask(taskId: $task->id);

		$events = $eventTools->listEvents(taskId: $task->id, type: 'TaskArchived')->events;
		self::assertCount(1, $events);
		self::assertSame($task->id, $events[0]->taskId);
	}

	public function testUnknownEventTypeThrows(): void
	{
		$user = Fixture::createUser();
		Fixture::createWorkspace($user);

		[, $eventTools] = $this->bootAs($user);

		$this->expectException(RuntimeException::class);
		$eventTools->listEvents(type: 'NotARealType');
	}

	/** @return array{0: TaskTools, 1: EventTools} */
	private function bootAs(User $user): array
	{
		$ctx = AppHarness::container()->get(McpUserContextInterface::class);
		assert($ctx instanceof McpUserContextInterface);
		$ctx->setUser($user);

		$actor = AppHarness::container()->get(ActorContextInterface::class);
		assert($actor instanceof ActorContextInterface);
		$actor->setAgent('cli', 'Test CLI');

		$taskTools = AppHarness::container()->get(TaskTools::class);
		assert($taskTools instanceof TaskTools);

		$eventTools = AppHarness::container()->get(EventTools::class);
		assert($eventTools instanceof EventTools);

		return [$taskTools, $eventTools];
	}
}
