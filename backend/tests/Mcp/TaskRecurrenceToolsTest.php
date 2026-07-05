<?php

declare(strict_types=1);

namespace Ukolio\Tests\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Mcp\Tool\TaskRecurrenceTools;
use Ukolio\Mcp\Tool\TaskTools;
use Ukolio\Model\Entity\User;
use Ukolio\Service\Actor\ActorContextInterface;
use Ukolio\Tests\Support\AppHarness;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(TaskRecurrenceTools::class)]
final class TaskRecurrenceToolsTest extends IntegrationTestCase
{
	public function testSetGetAndClear(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools, $recurrenceTools] = $this->bootAs($user);
		$task = $taskTools->createTask(projectId: $project->id, name: 'Weekly review', dueDate: '2026-06-01');

		self::assertNull($recurrenceTools->getTaskRecurrence($task->id));

		$set = $recurrenceTools->setTaskRecurrence($task->id, cadence: 'Weekly', interval: 2, weekday: 1);
		self::assertSame('Weekly', $set->cadence);
		self::assertSame(2, $set->interval);
		self::assertSame(1, $set->weekday);
		self::assertTrue($set->active);

		$fetched = $recurrenceTools->getTaskRecurrence($task->id);
		self::assertNotNull($fetched);
		self::assertSame('Weekly', $fetched->cadence);

		self::assertSame('Recurrence cleared.', $recurrenceTools->clearTaskRecurrence($task->id));
		self::assertNull($recurrenceTools->getTaskRecurrence($task->id));
	}

	public function testCreateTaskWithRecurrencePayload(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools, $recurrenceTools] = $this->bootAs($user);
		$task = $taskTools->createTask(
			projectId: $project->id,
			name: 'Daily chore',
			dueDate: '2026-06-01',
			recurrence: ['cadence' => 'Daily', 'interval' => 1],
		);

		$recurrence = $recurrenceTools->getTaskRecurrence($task->id);
		self::assertNotNull($recurrence);
		self::assertSame('Daily', $recurrence->cadence);
		self::assertTrue($recurrence->active);
	}

	public function testInvalidCadenceIsRejected(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools, $recurrenceTools] = $this->bootAs($user);
		$task = $taskTools->createTask(projectId: $project->id, name: 'Task');

		$this->expectException(RuntimeException::class);
		$recurrenceTools->setTaskRecurrence($task->id, cadence: 'Yearly');
	}

	/** @return array{0:TaskTools,1:TaskRecurrenceTools} */
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

		$recurrenceTools = AppHarness::container()->get(TaskRecurrenceTools::class);
		assert($recurrenceTools instanceof TaskRecurrenceTools);

		return [$taskTools, $recurrenceTools];
	}
}
