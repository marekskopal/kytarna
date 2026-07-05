<?php

declare(strict_types=1);

namespace Kytario\Tests\Controller;

use Kytario\Controller\TaskController;
use Kytario\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytario\Model\Entity\Project;
use Kytario\Model\Entity\User;
use Kytario\Model\Repository\StatusRepository;
use Kytario\Model\Repository\WorkflowRepository;
use Kytario\Tests\Support\Fixture;
use Kytario\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TaskController::class)]
final class TaskControllerTest extends IntegrationTestCase
{
	public function testCreateListAndGetTaskRoundTrip(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);

		$startStatus = $this->firstStatusId($project->id);

		$create = $this->request(
			'POST',
			'/api/projects/' . $project->id . '/tasks',
			body: [
				'statusId' => $startStatus,
				'name' => 'Write tests',
				'description' => 'Cover the codebase',
				'dueDate' => '2026-06-01',
			],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $create->getStatusCode());
		$task = $this->jsonBody($create);
		self::assertSame('Write tests', $task['name']);
		self::assertNotEmpty($task['code']);
		$taskId = self::intField($task['id']);
		$taskCode = self::stringField($task['code']);

		// List under project
		$list = $this->request('GET', '/api/projects/' . $project->id . '/tasks', authenticatedAs: $owner);
		self::assertCount(1, $this->jsonList($list));

		// Get by numeric ID (routes accept either int ID or PREFIX-N code)
		$getById = $this->request('GET', '/api/tasks/' . $taskId, authenticatedAs: $owner);
		self::assertSame(200, $getById->getStatusCode());

		// Get by code form
		$getByCode = $this->request('GET', '/api/tasks/' . $taskCode, authenticatedAs: $owner);
		self::assertSame(200, $getByCode->getStatusCode());

		// Workspace-wide listing returns same task
		$wsList = $this->request('GET', '/api/tasks', authenticatedAs: $owner);
		self::assertSame(200, $wsList->getStatusCode());
		$payload = $this->jsonBody($wsList);
		self::assertSame(1, $payload['count']);
		$payloadTasks = $payload['tasks'];
		self::assertIsArray($payloadTasks);
		self::assertCount(1, $payloadTasks);
	}

	public function testMoveTaskBetweenStatuses(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);

		[$todoId, $inProgressId] = $this->statusIds($project->id);

		$create = $this->request(
			'POST',
			'/api/projects/' . $project->id . '/tasks',
			body: ['statusId' => $todoId, 'name' => 'Move me', 'description' => null],
			authenticatedAs: $owner,
		);
		$code = self::stringField($this->jsonBody($create)['code']);

		$move = $this->request(
			'PUT',
			'/api/tasks/' . $code . '/move',
			body: ['statusId' => $inProgressId, 'position' => 0],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $move->getStatusCode());
		self::assertSame($inProgressId, $this->jsonBody($move)['statusId']);
	}

	public function testWorkspaceListPaginationAndStatusFilter(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		[$todoId, $inProgressId] = $this->statusIds($project->id);

		for ($i = 0; $i < 4; $i++) {
			$this->request(
				'POST',
				'/api/projects/' . $project->id . '/tasks',
				body: ['statusId' => $i % 2 === 0 ? $todoId : $inProgressId, 'name' => 'T' . $i, 'description' => null],
				authenticatedAs: $owner,
			);
		}

		$filtered = $this->request('GET', '/api/tasks?statusIds=' . $todoId . '&limit=10', authenticatedAs: $owner);
		self::assertSame(200, $filtered->getStatusCode());
		self::assertSame(2, $this->jsonBody($filtered)['count']);

		$paged = $this->request('GET', '/api/tasks?limit=2&offset=0', authenticatedAs: $owner);
		$pagedBody = $this->jsonBody($paged);
		$pagedTasks = $pagedBody['tasks'];
		self::assertIsArray($pagedTasks);
		self::assertCount(2, $pagedTasks);
		self::assertSame(4, $pagedBody['count']);
	}

	public function testWorkspaceListDueDateRangeFilter(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		[$todoId] = $this->statusIds($project->id);

		$create = function (string $name, ?string $dueDate) use ($project, $todoId, $owner): void {
			$this->request(
				'POST',
				'/api/projects/' . $project->id . '/tasks',
				body: ['statusId' => $todoId, 'name' => $name, 'description' => null, 'dueDate' => $dueDate],
				authenticatedAs: $owner,
			);
		};
		$create('May 10', '2026-05-10');
		$create('May 20', '2026-05-20');
		$create('Jun 05', '2026-06-05');
		$create('No due date', null);

		// Inclusive on both bounds — May window catches exactly the two May tasks.
		$may = $this->request('GET', '/api/tasks?dueFrom=2026-05-01&dueTo=2026-05-31', authenticatedAs: $owner);
		self::assertSame(200, $may->getStatusCode());
		self::assertSame(2, $this->jsonBody($may)['count']);

		// Open-ended lower bound (undated tasks never match a bound).
		$fromMid = $this->request('GET', '/api/tasks?dueFrom=2026-05-15', authenticatedAs: $owner);
		self::assertSame(2, $this->jsonBody($fromMid)['count']);

		// Open-ended upper bound.
		$untilMid = $this->request('GET', '/api/tasks?dueTo=2026-05-15', authenticatedAs: $owner);
		self::assertSame(1, $this->jsonBody($untilMid)['count']);

		// A malformed date is a 400, not a silent no-op.
		$bad = $this->request('GET', '/api/tasks?dueFrom=not-a-date', authenticatedAs: $owner);
		self::assertSame(400, $bad->getStatusCode());
	}

	public function testStartDateRoundTripAndOrderValidation(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$todoId = $this->firstStatusId($project->id);

		// Create with both start + due — both round-trip in the response.
		$create = $this->request('POST', '/api/projects/' . $project->id . '/tasks', body: [
			'statusId' => $todoId,
			'name' => 'Spanning task',
			'description' => null,
			'startDate' => '2026-05-10',
			'dueDate' => '2026-05-20',
		], authenticatedAs: $owner);
		self::assertSame(200, $create->getStatusCode());
		$created = $this->jsonBody($create);
		self::assertSame('2026-05-10', $created['startDate']);
		self::assertSame('2026-05-20', $created['dueDate']);
		$code = self::stringField($created['code']);

		// Updating the start date round-trips too.
		$update = $this->request('PUT', '/api/tasks/' . $code, body: [
			'statusId' => $todoId,
			'name' => 'Spanning task',
			'description' => null,
			'startDate' => '2026-05-12',
			'dueDate' => '2026-05-20',
		], authenticatedAs: $owner);
		self::assertSame(200, $update->getStatusCode());
		self::assertSame('2026-05-12', $this->jsonBody($update)['startDate']);

		// start > due is rejected by the provider invariant (422).
		$bad = $this->request('POST', '/api/projects/' . $project->id . '/tasks', body: [
			'statusId' => $todoId,
			'name' => 'Backwards',
			'description' => null,
			'startDate' => '2026-05-25',
			'dueDate' => '2026-05-20',
		], authenticatedAs: $owner);
		self::assertSame(422, $bad->getStatusCode());
	}

	public function testMalformedBodyDateIsRejectedWith422(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$todoId = $this->firstStatusId($project->id);

		// A malformed due date must answer 422, not crash with an uncaught date-parse error (500).
		$response = $this->request('POST', '/api/projects/' . $project->id . '/tasks', body: [
			'statusId' => $todoId,
			'name' => 'Bad date',
			'description' => null,
			'dueDate' => 'not-a-date',
		], authenticatedAs: $owner);
		self::assertSame(422, $response->getStatusCode());
	}

	public function testDeleteTaskRemovesIt(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$todoId = $this->firstStatusId($project->id);

		$create = $this->request(
			'POST',
			'/api/projects/' . $project->id . '/tasks',
			body: ['statusId' => $todoId, 'name' => 'Doomed', 'description' => null],
			authenticatedAs: $owner,
		);
		$code = self::stringField($this->jsonBody($create)['code']);

		$delete = $this->request('DELETE', '/api/tasks/' . $code, authenticatedAs: $owner);
		self::assertSame(200, $delete->getStatusCode());

		$get = $this->request('GET', '/api/tasks/' . $code, authenticatedAs: $owner);
		self::assertSame(404, $get->getStatusCode());
	}

	public function testGetTaskFromAnotherWorkspaceIsNotFound(): void
	{
		[, $intruder, , $taskCode] = $this->seedCrossWorkspace();

		$response = $this->request('GET', '/api/tasks/' . $taskCode, authenticatedAs: $intruder);
		self::assertSame(404, $response->getStatusCode());

		// Also assert the workspace-wide list does not leak the foreign task.
		$list = $this->request('GET', '/api/tasks', authenticatedAs: $intruder);
		self::assertSame(0, $this->jsonBody($list)['count']);
	}

	public function testCreateTaskInAnotherWorkspaceProjectIsNotFound(): void
	{
		[$projectInA, $intruder, $todoIdInA] = $this->seedCrossWorkspace();

		$response = $this->request(
			'POST',
			'/api/projects/' . $projectInA->id . '/tasks',
			body: ['statusId' => $todoIdInA, 'name' => 'Hijack', 'description' => null],
			authenticatedAs: $intruder,
		);
		self::assertSame(404, $response->getStatusCode());
	}

	public function testUpdateTaskFromAnotherWorkspaceIsNotFound(): void
	{
		[, $intruder, , $taskCode] = $this->seedCrossWorkspace();

		$response = $this->request(
			'PUT',
			'/api/tasks/' . $taskCode,
			body: ['name' => 'Renamed by intruder', 'description' => null],
			authenticatedAs: $intruder,
		);
		self::assertSame(404, $response->getStatusCode());
	}

	public function testMoveTaskFromAnotherWorkspaceIsNotFound(): void
	{
		[$projectInA, $intruder, , $taskCode] = $this->seedCrossWorkspace();
		$statusesInA = $this->statusIds($projectInA->id);

		$response = $this->request(
			'PUT',
			'/api/tasks/' . $taskCode . '/move',
			body: ['statusId' => $statusesInA[1], 'position' => 0],
			authenticatedAs: $intruder,
		);
		self::assertSame(404, $response->getStatusCode());
	}

	public function testDeleteTaskFromAnotherWorkspaceIsNotFound(): void
	{
		[, $intruder, , $taskCode] = $this->seedCrossWorkspace();

		$response = $this->request('DELETE', '/api/tasks/' . $taskCode, authenticatedAs: $intruder);
		self::assertSame(404, $response->getStatusCode());
	}

	/**
	 * Build the two-workspace scaffold used by every cross-workspace test:
	 * an owner with workspace A holding one task; a separate intruder in workspace B.
	 *
	 * @return array{0:Project,1:User,2:int,3:string}
	 *   [project in A, intruder user, first-status id in A, task code in A]
	 */
	private function seedCrossWorkspace(): array
	{
		$owner = Fixture::createUser(email: 'owner@example.com');
		$workspaceA = Fixture::createWorkspace($owner, 'A');
		$projectInA = Fixture::createProject($owner, $workspaceA);
		$todoIdInA = $this->firstStatusId($projectInA->id);

		$create = $this->request(
			'POST',
			'/api/projects/' . $projectInA->id . '/tasks',
			body: ['statusId' => $todoIdInA, 'name' => 'Owner task', 'description' => null],
			authenticatedAs: $owner,
		);
		assert($create->getStatusCode() === 200);
		$taskCode = self::stringField($this->jsonBody($create)['code']);

		$intruder = Fixture::createUser(email: 'intruder@example.com');
		Fixture::createWorkspace($intruder, 'B');

		return [$projectInA, $intruder, $todoIdInA, $taskCode];
	}

	/** @return array{0:int,1:int} */
	private function statusIds(int $projectId): array
	{
		$workflowRepo = $this->container->get(WorkflowRepository::class);
		assert($workflowRepo instanceof WorkflowRepository);
		$workflow = $workflowRepo->findByProject($projectId);
		assert($workflow !== null);

		$statusRepo = $this->container->get(StatusRepository::class);
		assert($statusRepo instanceof StatusRepository);
		$statuses = [];
		foreach ($statusRepo->findByWorkflow($workflow->id) as $status) {
			$statuses[] = $status->id;
		}
		return [$statuses[0], $statuses[1]];
	}

	private function firstStatusId(int $projectId): int
	{
		return $this->statusIds($projectId)[0];
	}

	public function testCreateTaskDefaultsAssigneeToCreator(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$todoId = $this->firstStatusId($project->id);

		$response = $this->request(
			'POST',
			'/api/projects/' . $project->id . '/tasks',
			body: ['statusId' => $todoId, 'name' => 'My task', 'description' => null],
			authenticatedAs: $owner,
		);

		self::assertSame(200, $response->getStatusCode());
		self::assertSame($owner->id, $this->jsonBody($response)['assigneeId']);
	}

	public function testCreateTaskWithExplicitAssigneeForWorkspaceMember(): void
	{
		$owner = Fixture::createUser();
		$member = Fixture::createUser(email: 'member@example.com');
		$workspace = Fixture::createWorkspace($owner);
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);
		$project = Fixture::createProject($owner, $workspace);
		$todoId = $this->firstStatusId($project->id);

		$response = $this->request(
			'POST',
			'/api/projects/' . $project->id . '/tasks',
			body: ['statusId' => $todoId, 'name' => 'Pair task', 'description' => null, 'assigneeId' => $member->id],
			authenticatedAs: $owner,
		);

		self::assertSame(200, $response->getStatusCode());
		self::assertSame($member->id, $this->jsonBody($response)['assigneeId']);
	}

	public function testCreateTaskWithNonMemberAssigneeIsRejected(): void
	{
		$owner = Fixture::createUser();
		$outsider = Fixture::createUser(email: 'outsider@example.com');
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$todoId = $this->firstStatusId($project->id);

		$response = $this->request(
			'POST',
			'/api/projects/' . $project->id . '/tasks',
			body: ['statusId' => $todoId, 'name' => 'Bad', 'description' => null, 'assigneeId' => $outsider->id],
			authenticatedAs: $owner,
		);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testCreateTaskWithNullAssigneeIsAllowed(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$todoId = $this->firstStatusId($project->id);

		$response = $this->request(
			'POST',
			'/api/projects/' . $project->id . '/tasks',
			body: ['statusId' => $todoId, 'name' => 'Unassigned', 'description' => null, 'assigneeId' => null],
			authenticatedAs: $owner,
		);

		self::assertSame(200, $response->getStatusCode());
		self::assertNull($this->jsonBody($response)['assigneeId']);
	}

	public function testUpdateTaskClearsAndChangesAssignee(): void
	{
		$owner = Fixture::createUser();
		$member = Fixture::createUser(email: 'm@example.com');
		$workspace = Fixture::createWorkspace($owner);
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);
		$project = Fixture::createProject($owner, $workspace);
		$todoId = $this->firstStatusId($project->id);

		$create = $this->request(
			'POST',
			'/api/projects/' . $project->id . '/tasks',
			body: ['statusId' => $todoId, 'name' => 'T', 'description' => null],
			authenticatedAs: $owner,
		);
		$code = self::stringField($this->jsonBody($create)['code']);

		// Update without assigneeId leaves it unchanged (owner).
		$noChange = $this->request(
			'PUT',
			'/api/tasks/' . $code,
			body: ['statusId' => $todoId, 'name' => 'T2', 'description' => null],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $noChange->getStatusCode());
		self::assertSame($owner->id, $this->jsonBody($noChange)['assigneeId']);

		// Reassign.
		$reassign = $this->request(
			'PUT',
			'/api/tasks/' . $code,
			body: ['statusId' => $todoId, 'name' => 'T2', 'description' => null, 'assigneeId' => $member->id],
			authenticatedAs: $owner,
		);
		self::assertSame($member->id, $this->jsonBody($reassign)['assigneeId']);

		// Clear with null.
		$clear = $this->request(
			'PUT',
			'/api/tasks/' . $code,
			body: ['statusId' => $todoId, 'name' => 'T2', 'description' => null, 'assigneeId' => null],
			authenticatedAs: $owner,
		);
		self::assertNull($this->jsonBody($clear)['assigneeId']);
	}

	public function testWorkspaceListFiltersByAssignee(): void
	{
		$owner = Fixture::createUser();
		$member = Fixture::createUser(email: 'a@example.com');
		$workspace = Fixture::createWorkspace($owner);
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);
		$project = Fixture::createProject($owner, $workspace);
		$todoId = $this->firstStatusId($project->id);

		$this->request(
			'POST',
			'/api/projects/' . $project->id . '/tasks',
			body: ['statusId' => $todoId, 'name' => 'A', 'description' => null],
			authenticatedAs: $owner,
		);
		$this->request(
			'POST',
			'/api/projects/' . $project->id . '/tasks',
			body: ['statusId' => $todoId, 'name' => 'B', 'description' => null, 'assigneeId' => $member->id],
			authenticatedAs: $owner,
		);

		$list = $this->request('GET', '/api/tasks?assigneeIds=' . $member->id, authenticatedAs: $owner);
		self::assertSame(200, $list->getStatusCode());
		self::assertSame(1, $this->jsonBody($list)['count']);
	}

	public function testArchiveAndUnarchiveTaskRoundTrip(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$todoId = $this->firstStatusId($project->id);

		$created = $this->request(
			'POST',
			'/api/projects/' . $project->id . '/tasks',
			body: ['statusId' => $todoId, 'name' => 'Archive me', 'description' => null],
			authenticatedAs: $owner,
		);
		$taskId = self::intField($this->jsonBody($created)['id']);

		// Archiving stamps archivedAt and the task drops out of the default workspace list.
		$archive = $this->request('POST', '/api/tasks/' . $taskId . '/archive', authenticatedAs: $owner);
		self::assertSame(200, $archive->getStatusCode());
		self::assertNotNull($this->jsonBody($archive)['archivedAt']);

		$activeList = $this->request('GET', '/api/tasks', authenticatedAs: $owner);
		self::assertSame(0, $this->jsonBody($activeList)['count']);

		// archived=archived returns only archived; archived=all returns both.
		$archivedList = $this->request('GET', '/api/tasks?archived=archived', authenticatedAs: $owner);
		self::assertSame(1, $this->jsonBody($archivedList)['count']);

		$allList = $this->request('GET', '/api/tasks?archived=all', authenticatedAs: $owner);
		self::assertSame(1, $this->jsonBody($allList)['count']);

		// Archived tasks are hidden from the board.
		$board = $this->request('GET', '/api/projects/' . $project->id . '/board', authenticatedAs: $owner);
		$boardTasks = $this->jsonBody($board)['tasks'];
		self::assertIsArray($boardTasks);
		self::assertCount(0, $boardTasks);

		// Unarchiving restores it everywhere.
		$unarchive = $this->request('POST', '/api/tasks/' . $taskId . '/unarchive', authenticatedAs: $owner);
		self::assertSame(200, $unarchive->getStatusCode());
		self::assertNull($this->jsonBody($unarchive)['archivedAt']);

		$restored = $this->request('GET', '/api/tasks', authenticatedAs: $owner);
		self::assertSame(1, $this->jsonBody($restored)['count']);
	}

	public function testSearchTreatsLikeWildcardsLiterally(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$statusId = $this->firstStatusId($project->id);

		foreach (['Plain task', '100% done', 'under_score'] as $name) {
			$create = $this->request(
				'POST',
				'/api/projects/' . $project->id . '/tasks',
				body: ['statusId' => $statusId, 'name' => $name],
				authenticatedAs: $owner,
			);
			self::assertSame(200, $create->getStatusCode());
		}

		// "%" must match only the literal percent sign, not every row.
		$percent = $this->request('GET', '/api/tasks?search=' . urlencode('%'), authenticatedAs: $owner);
		self::assertSame(1, $this->jsonBody($percent)['count']);

		// "_" must match only the literal underscore, not any single character.
		$underscore = $this->request('GET', '/api/tasks?search=' . urlencode('_'), authenticatedAs: $owner);
		self::assertSame(1, $this->jsonBody($underscore)['count']);
	}

	public function testCreateTaskWithEmptyNameIsRejected(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);

		$response = $this->request(
			'POST',
			'/api/projects/' . $project->id . '/tasks',
			body: ['statusId' => $this->firstStatusId($project->id), 'name' => '   '],
			authenticatedAs: $owner,
		);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testCreateTaskWithOverlongNameIsRejected(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);

		$response = $this->request(
			'POST',
			'/api/projects/' . $project->id . '/tasks',
			body: ['statusId' => $this->firstStatusId($project->id), 'name' => str_repeat('a', 256)],
			authenticatedAs: $owner,
		);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testCreateTaskWithOverlongDescriptionIsRejected(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);

		$response = $this->request(
			'POST',
			'/api/projects/' . $project->id . '/tasks',
			body: [
				'statusId' => $this->firstStatusId($project->id),
				'name' => 'Valid name',
				'description' => str_repeat('a', 50001),
			],
			authenticatedAs: $owner,
		);

		self::assertSame(422, $response->getStatusCode());
	}
}
