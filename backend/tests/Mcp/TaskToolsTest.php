<?php

declare(strict_types=1);

namespace Ukolio\Tests\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Mcp\Tool\TaskTools;
use Ukolio\Mcp\Tool\WorkflowTools;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Model\Entity\User;
use Ukolio\Service\Actor\ActorContextInterface;
use Ukolio\Tests\Support\AppHarness;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(TaskTools::class)]
#[CoversClass(WorkflowTools::class)]
final class TaskToolsTest extends IntegrationTestCase
{
	public function testCreateTaskDefaultsToStartStatusAndIsMarkedAgentCreated(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools, $workflowTools] = $this->bootAs($user);

		$task = $taskTools->createTask(projectId: $project->id, name: 'Agent task');

		self::assertSame('Agent task', $task->name);

		// The Start status is the first in the default workflow.
		$statuses = $workflowTools->listStatuses($project->id);
		self::assertSame($statuses->statuses[0]->id, $task->statusId);

		// Verify the task was attributed to an agent (ActorContext was flipped to Agent in bootAs).
		$pdo = AppHarness::pdo();
		$stmt = $pdo->prepare('SELECT created_by_agent FROM tasks WHERE id = :id');
		if ($stmt === false) {
			self::fail('Failed to prepare SELECT statement');
		}
		$stmt->execute(['id' => $task->id]);
		self::assertSame(1, (int) $stmt->fetchColumn());
	}

	public function testCreateTaskHonoursStatusName(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools] = $this->bootAs($user);

		$task = $taskTools->createTask(projectId: $project->id, name: 'In progress task', statusName: 'In Progress');
		self::assertSame('In progress task', $task->name);
	}

	public function testMoveTaskByStatusName(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools, $workflowTools] = $this->bootAs($user);

		$task = $taskTools->createTask(projectId: $project->id, name: 'Move me');
		$moved = $taskTools->moveTask(taskId: $task->id, statusName: 'Done');

		$statuses = $workflowTools->listStatuses($project->id);
		$doneId = null;
		foreach ($statuses->statuses as $status) {
			if ($status->name === 'Done') {
				$doneId = $status->id;
			}
		}
		self::assertSame($doneId, $moved->statusId);
	}

	public function testFindTaskByNamePrefersExactMatchOverSubstring(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools] = $this->bootAs($user);
		$taskTools->createTask(projectId: $project->id, name: 'Pay invoice');
		$taskTools->createTask(projectId: $project->id, name: 'Pay');

		$found = $taskTools->findTaskByName($project->id, 'Pay');
		self::assertNotNull($found);
		self::assertSame('Pay', $found->name);
	}

	public function testGetTaskByCode(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools] = $this->bootAs($user);
		$task = $taskTools->createTask(projectId: $project->id, name: 'Codeable');

		$fetched = $taskTools->getTask($task->code);
		self::assertSame($task->id, $fetched->id);
	}

	public function testDeleteTask(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools] = $this->bootAs($user);
		$task = $taskTools->createTask(projectId: $project->id, name: 'Doomed');

		$taskTools->deleteTask($task->id);

		$this->expectException(\RuntimeException::class);
		$taskTools->getTask($task->id);
	}

	public function testCreateTaskDefaultsAssigneeToCurrentMcpUser(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools] = $this->bootAs($user);
		$task = $taskTools->createTask(projectId: $project->id, name: 'Mine');

		self::assertSame($user->id, $task->assigneeId);
	}

	public function testUpdateTaskAssigneeSetClearAndUnchanged(): void
	{
		$user = Fixture::createUser();
		$member = Fixture::createUser(email: 'member@example.com');
		$workspace = Fixture::createWorkspace($user);
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools] = $this->bootAs($user);
		$task = $taskTools->createTask(projectId: $project->id, name: 'T');

		// Default assignee = user.
		self::assertSame($user->id, $task->assigneeId);

		// Reassign to member.
		$reassigned = $taskTools->updateTask(taskId: $task->id, assigneeId: $member->id);
		self::assertSame($member->id, $reassigned->assigneeId);

		// Update with no assignee* args leaves it unchanged.
		$nameOnly = $taskTools->updateTask(taskId: $task->id, name: 'T2');
		self::assertSame($member->id, $nameOnly->assigneeId);

		// Clear via the clearAssignee flag.
		$cleared = $taskTools->updateTask(taskId: $task->id, clearAssignee: true);
		self::assertNull($cleared->assigneeId);
	}

	public function testCreateTaskWithNonMemberAssigneeFails(): void
	{
		$user = Fixture::createUser();
		$outsider = Fixture::createUser(email: 'outsider@example.com');
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools] = $this->bootAs($user);

		$this->expectException(\RuntimeException::class);
		$taskTools->createTask(projectId: $project->id, name: 'Bad', assigneeId: $outsider->id);
	}

	public function testStartDateCreateUpdateClearAndOrderValidation(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools] = $this->bootAs($user);

		// Create carries startDate through to the DTO.
		$task = $taskTools->createTask(projectId: $project->id, name: 'Spanning', dueDate: '2026-05-20', startDate: '2026-05-10');
		self::assertSame('2026-05-10', $task->startDate);

		// Omitting startDate on update leaves it unchanged; empty string clears it.
		$nameOnly = $taskTools->updateTask(taskId: $task->id, name: 'Spanning 2');
		self::assertSame('2026-05-10', $nameOnly->startDate);
		$cleared = $taskTools->updateTask(taskId: $task->id, startDate: '');
		self::assertNull($cleared->startDate);

		// start > due is rejected.
		$this->expectException(\RuntimeException::class);
		$taskTools->createTask(projectId: $project->id, name: 'Backwards', dueDate: '2026-05-20', startDate: '2026-05-25');
	}

	public function testCreateTaskDefaultsToWorkspaceDefaultPriority(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools] = $this->bootAs($user);

		$task = $taskTools->createTask(projectId: $project->id, name: 'No priority specified');

		// Seeded default is "Medium".
		self::assertSame('Medium', $task->priorityName);
	}

	public function testCreateTaskAcceptsLegacyPriorityString(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools] = $this->bootAs($user);

		$task = $taskTools->createTask(projectId: $project->id, name: 'Crit', priorityName: 'high');
		self::assertSame('High', $task->priorityName);
	}

	public function testCreateTaskAcceptsPriorityIdAndNameInterchangeably(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools] = $this->bootAs($user);

		// Fetch the workspace's priorities through the HTTP endpoint to learn ids.
		$list = $this->jsonList($this->request(
			'GET',
			'/api/workspaces/' . $workspace->id . '/priorities',
			authenticatedAs: $user,
		));
		$highId = self::intField($list[0]['id']);

		$byId = $taskTools->createTask(projectId: $project->id, name: 'By id', priorityId: $highId);
		$byName = $taskTools->createTask(projectId: $project->id, name: 'By name', priorityName: 'High');

		self::assertSame($highId, $byId->priorityId);
		self::assertSame($highId, $byName->priorityId);
	}

	public function testArchiveHidesTaskFromListByDefault(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		[$taskTools] = $this->bootAs($user);

		$task = $taskTools->createTask(projectId: $project->id, name: 'Archive me');

		$archived = $taskTools->archiveTask($task->id);
		self::assertTrue($archived->archived);
		self::assertNotNull($archived->archivedAt);

		// Default list_tasks hides archived; includeArchived=true brings it back.
		self::assertCount(0, $taskTools->listTasks($project->id)->tasks);
		self::assertCount(1, $taskTools->listTasks($project->id, includeArchived: true)->tasks);

		$unarchived = $taskTools->unarchiveTask($task->id);
		self::assertFalse($unarchived->archived);
		self::assertCount(1, $taskTools->listTasks($project->id)->tasks);
	}

	/** @return array{0:TaskTools,1:WorkflowTools} */
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

		$workflowTools = AppHarness::container()->get(WorkflowTools::class);
		assert($workflowTools instanceof WorkflowTools);

		return [$taskTools, $workflowTools];
	}
}
