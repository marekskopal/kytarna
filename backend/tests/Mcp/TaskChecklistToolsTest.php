<?php

declare(strict_types=1);

namespace Ukolio\Tests\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Mcp\Tool\TaskChecklistTools;
use Ukolio\Mcp\Tool\TaskTools;
use Ukolio\Model\Entity\User;
use Ukolio\Service\Actor\ActorContextInterface;
use Ukolio\Tests\Support\AppHarness;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(TaskChecklistTools::class)]
final class TaskChecklistToolsTest extends IntegrationTestCase
{
	public function testAddListToggleAndDelete(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools, $checklistTools] = $this->bootAs($user);
		$task = $taskTools->createTask(projectId: $project->id, name: 'Task');

		$item = $checklistTools->addChecklistItem($task->id, 'Step one', dueDate: '2026-08-01', assigneeId: $user->id);
		self::assertSame('Step one', $item->text);
		self::assertSame('2026-08-01', $item->dueDate);
		self::assertSame($user->id, $item->assigneeId);
		self::assertFalse($item->checked);

		$checklistTools->addChecklistItem($task->id, 'Step two');

		$toggled = $checklistTools->toggleChecklistItem($item->id, true);
		self::assertTrue($toggled->checked);

		$list = $checklistTools->listTaskChecklist($task->id);
		self::assertSame(2, $list->total);
		self::assertSame(1, $list->done);
		self::assertCount(2, $list->items);

		$updated = $checklistTools->updateChecklistItem($item->id, text: 'Renamed', dueDate: '', clearAssignee: true);
		self::assertSame('Renamed', $updated->text);
		self::assertNull($updated->dueDate);
		self::assertNull($updated->assigneeId);
		self::assertTrue($updated->checked);

		self::assertSame('Checklist item deleted.', $checklistTools->deleteChecklistItem($item->id));
		self::assertSame(1, $checklistTools->listTaskChecklist($task->id)->total);
	}

	public function testItemFromAnotherWorkspaceIsNotFound(): void
	{
		$user = Fixture::createUser();
		Fixture::createWorkspace($user);
		[, $checklistTools] = $this->bootAs($user);

		$this->expectException(RuntimeException::class);
		$checklistTools->toggleChecklistItem(999999, true);
	}

	/** @return array{0:TaskTools,1:TaskChecklistTools} */
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

		$checklistTools = AppHarness::container()->get(TaskChecklistTools::class);
		assert($checklistTools instanceof TaskChecklistTools);

		return [$taskTools, $checklistTools];
	}
}
