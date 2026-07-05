<?php

declare(strict_types=1);

namespace Kytario\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use Kytario\Controller\PriorityController;
use Kytario\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytario\Model\Repository\StatusRepository;
use Kytario\Model\Repository\WorkflowRepository;
use Kytario\Tests\Support\AppHarness;
use Kytario\Tests\Support\Fixture;
use Kytario\Tests\Support\IntegrationTestCase;

#[CoversClass(PriorityController::class)]
final class PriorityControllerTest extends IntegrationTestCase
{
	public function testSeededPrioritiesAreReturned(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);

		$list = $this->request('GET', '/api/workspaces/' . $workspace->id . '/priorities', authenticatedAs: $owner);
		self::assertSame(200, $list->getStatusCode());

		$body = $this->jsonList($list);
		self::assertCount(3, $body);
		self::assertSame('High', $body[0]['name']);
		self::assertSame('Medium', $body[1]['name']);
		self::assertSame('Low', $body[2]['name']);
		self::assertTrue($body[1]['isDefault']);
		self::assertFalse($body[0]['isDefault']);
		self::assertFalse($body[2]['isDefault']);
	}

	public function testCreateUpdateDeletePriority(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);

		$create = $this->request(
			'POST',
			'/api/workspaces/' . $workspace->id . '/priorities',
			body: ['name' => 'Critical', 'color' => '#ff0000', 'isDefault' => false],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $create->getStatusCode());
		$body = $this->jsonBody($create);
		self::assertSame('Critical', $body['name']);
		self::assertSame('#ff0000', $body['color']);
		$priorityId = self::intField($body['id']);

		$update = $this->request(
			'PUT',
			'/api/workspaces/' . $workspace->id . '/priorities/' . $priorityId,
			body: ['name' => 'Blocker', 'color' => '#cc0000', 'isDefault' => false],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $update->getStatusCode());
		self::assertSame('Blocker', $this->jsonBody($update)['name']);

		$delete = $this->request('DELETE', '/api/workspaces/' . $workspace->id . '/priorities/' . $priorityId, authenticatedAs: $owner);
		self::assertSame(200, $delete->getStatusCode());
	}

	public function testSettingIsDefaultClearsTheFlagOnSiblings(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);

		// Find the seeded "High" id and mark it default.
		$list = $this->jsonList($this->request(
			'GET',
			'/api/workspaces/' . $workspace->id . '/priorities',
			authenticatedAs: $owner,
		));
		$highId = self::intField($list[0]['id']);

		$update = $this->request(
			'PUT',
			'/api/workspaces/' . $workspace->id . '/priorities/' . $highId,
			body: ['name' => 'High', 'color' => '#fdecea', 'isDefault' => true],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $update->getStatusCode());

		$after = $this->jsonList($this->request(
			'GET',
			'/api/workspaces/' . $workspace->id . '/priorities',
			authenticatedAs: $owner,
		));
		$flags = array_map(static fn (array $p): bool => (bool) $p['isDefault'], $after);
		self::assertSame([true, false, false], $flags);
	}

	public function testMovePriorityShiftsSiblings(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);

		$list = $this->jsonList($this->request(
			'GET',
			'/api/workspaces/' . $workspace->id . '/priorities',
			authenticatedAs: $owner,
		));
		// Move "Low" (position 2) to position 0.
		$lowId = self::intField($list[2]['id']);

		$move = $this->request(
			'PUT',
			'/api/workspaces/' . $workspace->id . '/priorities/' . $lowId . '/move',
			body: ['position' => 0],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $move->getStatusCode());

		$reordered = $this->jsonList($this->request(
			'GET',
			'/api/workspaces/' . $workspace->id . '/priorities',
			authenticatedAs: $owner,
		));
		$names = array_map(static fn (array $p): mixed => $p['name'], $reordered);
		self::assertSame(['Low', 'High', 'Medium'], $names);
	}

	public function testMovePriorityFromAnotherWorkspaceIsNotFound(): void
	{
		$owner = Fixture::createUser(email: 'owner@example.com');
		$workspaceA = Fixture::createWorkspace($owner, 'A');
		$priorities = $this->jsonList($this->request(
			'GET',
			'/api/workspaces/' . $workspaceA->id . '/priorities',
			authenticatedAs: $owner,
		));
		$foreignPriorityId = self::intField($priorities[0]['id']);

		$intruder = Fixture::createUser(email: 'intruder@example.com');
		$workspaceB = Fixture::createWorkspace($intruder, 'B');

		// A priority id from workspace A must not be reachable through workspace B's scope.
		$response = $this->request(
			'PUT',
			'/api/workspaces/' . $workspaceB->id . '/priorities/' . $foreignPriorityId . '/move',
			body: ['position' => 0],
			authenticatedAs: $intruder,
		);

		self::assertSame(404, $response->getStatusCode());
	}

	public function testDeletingPriorityWithTasksReturns409(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);

		$list = $this->jsonList($this->request(
			'GET',
			'/api/workspaces/' . $workspace->id . '/priorities',
			authenticatedAs: $owner,
		));
		$mediumId = self::intField($list[1]['id']);

		$workflowRepo = AppHarness::container()->get(WorkflowRepository::class);
		assert($workflowRepo instanceof WorkflowRepository);
		$workflow = $workflowRepo->findByProject($project->id);
		assert($workflow !== null);
		$statusRepo = AppHarness::container()->get(StatusRepository::class);
		assert($statusRepo instanceof StatusRepository);
		$statusId = 0;
		foreach ($statusRepo->findByWorkflow($workflow->id) as $status) {
			$statusId = $status->id;
			break;
		}
		assert($statusId > 0);

		$created = $this->request(
			'POST',
			'/api/projects/' . $project->id . '/tasks',
			body: ['statusId' => $statusId, 'name' => 'Sample', 'priorityId' => $mediumId],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $created->getStatusCode());

		$delete = $this->request('DELETE', '/api/workspaces/' . $workspace->id . '/priorities/' . $mediumId, authenticatedAs: $owner);
		self::assertSame(409, $delete->getStatusCode());
		$body = $this->jsonBody($delete);
		self::assertSame(1, $body['dependentTaskCount']);
	}

	public function testMemberCannotManagePriorities(): void
	{
		$owner = Fixture::createUser(email: 'o@example.com');
		$member = Fixture::createUser(email: 'm@example.com');
		$workspace = Fixture::createWorkspace($owner);
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);

		$readAllowed = $this->request('GET', '/api/workspaces/' . $workspace->id . '/priorities', authenticatedAs: $member);
		self::assertSame(200, $readAllowed->getStatusCode());

		$writeDenied = $this->request(
			'POST',
			'/api/workspaces/' . $workspace->id . '/priorities',
			body: ['name' => 'Trivial', 'color' => '#aaaaaa', 'isDefault' => false],
			authenticatedAs: $member,
		);
		self::assertSame(401, $writeDenied->getStatusCode());
	}
}
