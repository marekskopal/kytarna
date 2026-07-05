<?php

declare(strict_types=1);

namespace Ukolio\Tests\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Mcp\Tool\TaskRelationTools;
use Ukolio\Mcp\Tool\TaskTools;
use Ukolio\Model\Entity\User;
use Ukolio\Service\Actor\ActorContextInterface;
use Ukolio\Tests\Support\AppHarness;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(TaskRelationTools::class)]
final class TaskRelationToolsTest extends IntegrationTestCase
{
	public function testCreateSubtaskCreatesTaskAndParentRelation(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools, $relationTools] = $this->bootAs($user);

		$parent = $taskTools->createTask(projectId: $project->id, name: 'Epic');

		$child = $relationTools->createSubtask($parent->id, 'Step one', description: 'First step', priorityName: 'High');
		self::assertSame('Step one', $child->name);
		self::assertSame('First step', $child->description);
		self::assertSame('High', $child->priorityName);
		self::assertSame('To Do', $child->statusName);
		self::assertSame($project->id, $child->projectId);

		$relations = $relationTools->listTaskRelations($parent->id);
		self::assertCount(1, $relations->outgoing);
		self::assertSame('Parent', $relations->outgoing[0]->type);
		self::assertSame($child->id, $relations->outgoing[0]->targetTaskId);
	}

	public function testCreateSubtaskOnUnknownParentFails(): void
	{
		$user = Fixture::createUser();
		Fixture::createWorkspace($user);

		[, $relationTools] = $this->bootAs($user);

		$this->expectException(\RuntimeException::class);
		$relationTools->createSubtask(999999, 'Orphan');
	}

	/** @return array{0:TaskTools,1:TaskRelationTools} */
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

		$relationTools = AppHarness::container()->get(TaskRelationTools::class);
		assert($relationTools instanceof TaskRelationTools);

		return [$taskTools, $relationTools];
	}
}
