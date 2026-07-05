<?php

declare(strict_types=1);

namespace Ukolio\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use Ukolio\Controller\TaskTemplateController;
use Ukolio\Model\Repository\StatusRepository;
use Ukolio\Model\Repository\WorkflowRepository;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(TaskTemplateController::class)]
final class TaskTemplateControllerTest extends IntegrationTestCase
{
	public function testSaveListAndDeleteTemplateRoundTrip(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$todoId = $this->firstStatusId($project->id);

		$tag = $this->jsonBody($this->request(
			'POST',
			'/api/workspaces/' . $workspace->id . '/tags',
			body: ['name' => 'onboarding', 'color' => '#00aa00'],
			authenticatedAs: $owner,
		));
		$tagId = self::intField($tag['id']);

		$create = $this->request(
			'POST',
			'/api/projects/' . $project->id . '/tasks',
			body: [
				'statusId' => $todoId,
				'name' => 'Kickoff checklist',
				'description' => 'Step 1…',
				'priority' => 'High',
				'tagIds' => [$tagId],
			],
			authenticatedAs: $owner,
		);
		$taskId = self::intField($this->jsonBody($create)['id']);

		$save = $this->request(
			'POST',
			'/api/tasks/' . $taskId . '/save-as-template',
			body: ['name' => 'Customer kickoff'],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $save->getStatusCode());
		$template = $this->jsonBody($save);
		self::assertSame('Customer kickoff', $template['name']);
		$payload = $template['payload'];
		assert(is_array($payload));
		self::assertSame('Kickoff checklist', $payload['name']);
		self::assertSame('Step 1…', $payload['description']);
		self::assertSame([$tagId], $payload['tagIds']);
		$templateId = self::intField($template['id']);

		// Duplicate template names are rejected.
		$conflict = $this->request(
			'POST',
			'/api/tasks/' . $taskId . '/save-as-template',
			body: ['name' => 'Customer kickoff'],
			authenticatedAs: $owner,
		);
		self::assertSame(422, $conflict->getStatusCode());

		$list = $this->request('GET', '/api/workspaces/' . $workspace->id . '/task-templates', authenticatedAs: $owner);
		self::assertSame(200, $list->getStatusCode());
		self::assertCount(1, $this->jsonList($list));

		$delete = $this->request('DELETE', '/api/task-templates/' . $templateId, authenticatedAs: $owner);
		self::assertSame(200, $delete->getStatusCode());

		$emptyList = $this->request('GET', '/api/workspaces/' . $workspace->id . '/task-templates', authenticatedAs: $owner);
		self::assertCount(0, $this->jsonList($emptyList));
	}

	public function testTemplatesAreNotVisibleAcrossWorkspaces(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$todoId = $this->firstStatusId($project->id);

		$create = $this->request(
			'POST',
			'/api/projects/' . $project->id . '/tasks',
			body: ['statusId' => $todoId, 'name' => 'Secret', 'description' => null, 'priority' => 'Medium'],
			authenticatedAs: $owner,
		);
		$taskId = self::intField($this->jsonBody($create)['id']);

		$save = $this->request(
			'POST',
			'/api/tasks/' . $taskId . '/save-as-template',
			body: ['name' => 'Secret template'],
			authenticatedAs: $owner,
		);
		$templateId = self::intField($this->jsonBody($save)['id']);

		$outsider = Fixture::createUser();
		Fixture::createWorkspace($outsider);

		$list = $this->request('GET', '/api/workspaces/' . $workspace->id . '/task-templates', authenticatedAs: $outsider);
		self::assertSame(401, $list->getStatusCode());

		$delete = $this->request('DELETE', '/api/task-templates/' . $templateId, authenticatedAs: $outsider);
		self::assertSame(404, $delete->getStatusCode());

		$saveForeign = $this->request(
			'POST',
			'/api/tasks/' . $taskId . '/save-as-template',
			body: ['name' => 'Stolen'],
			authenticatedAs: $outsider,
		);
		self::assertSame(404, $saveForeign->getStatusCode());
	}

	private function firstStatusId(int $projectId): int
	{
		$workflowRepo = $this->container->get(WorkflowRepository::class);
		assert($workflowRepo instanceof WorkflowRepository);
		$workflow = $workflowRepo->findByProject($projectId);
		assert($workflow !== null);

		$statusRepo = $this->container->get(StatusRepository::class);
		assert($statusRepo instanceof StatusRepository);
		foreach ($statusRepo->findByWorkflow($workflow->id) as $status) {
			return $status->id;
		}

		self::fail('Project has no statuses.');
	}
}
