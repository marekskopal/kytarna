<?php

declare(strict_types=1);

namespace Ukolio\Tests\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Mcp\Tool\TaskTemplateTools;
use Ukolio\Mcp\Tool\TaskTools;
use Ukolio\Model\Entity\User;
use Ukolio\Service\Actor\ActorContextInterface;
use Ukolio\Tests\Support\AppHarness;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(TaskTemplateTools::class)]
final class TaskTemplateToolsTest extends IntegrationTestCase
{
	public function testSaveListAndInstantiateTemplate(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools, $templateTools] = $this->bootAs($user);

		$task = $taskTools->createTask(
			projectId: $project->id,
			name: 'Release checklist',
			description: 'Tag, build, deploy',
			priorityName: 'High',
			statusName: 'In Progress',
		);

		$template = $templateTools->saveTaskAsTemplate($task->id, 'Release');
		self::assertSame('Release', $template->name);
		self::assertSame('Release checklist', $template->taskName);
		self::assertSame('Tag, build, deploy', $template->description);

		$list = $templateTools->listTaskTemplates();
		self::assertCount(1, $list->templates);

		// Instantiation defaults to the Start status and the template's content.
		$created = $templateTools->createTaskFromTemplate($template->id, $project->id);
		self::assertSame('Release checklist', $created->name);
		self::assertSame('Tag, build, deploy', $created->description);
		self::assertSame('High', $created->priorityName);
		self::assertSame('To Do', $created->statusName);
		self::assertNotSame($task->id, $created->id);

		// Name and status overrides are honoured.
		$overridden = $templateTools->createTaskFromTemplate($template->id, $project->id, name: 'v2 release', statusName: 'Done');
		self::assertSame('v2 release', $overridden->name);
		self::assertSame('Done', $overridden->statusName);
	}

	public function testCreateTaskFromUnknownTemplateFails(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[, $templateTools] = $this->bootAs($user);

		$this->expectException(RuntimeException::class);
		$templateTools->createTaskFromTemplate(999999, $project->id);
	}

	public function testDuplicateTaskTool(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools] = $this->bootAs($user);

		$task = $taskTools->createTask(projectId: $project->id, name: 'Repeat me', description: 'Twice');

		$copy = $taskTools->duplicateTask($task->id);
		self::assertSame('Repeat me (copy)', $copy->name);
		self::assertSame('Twice', $copy->description);
		self::assertSame($task->statusId, $copy->statusId);
		self::assertNotSame($task->id, $copy->id);

		$named = $taskTools->duplicateTask($task->code, name: 'Third time');
		self::assertSame('Third time', $named->name);
	}

	/** @return array{0:TaskTools,1:TaskTemplateTools} */
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

		$templateTools = AppHarness::container()->get(TaskTemplateTools::class);
		assert($templateTools instanceof TaskTemplateTools);

		return [$taskTools, $templateTools];
	}
}
