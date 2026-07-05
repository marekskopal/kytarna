<?php

declare(strict_types=1);

namespace Kytario\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use Kytario\Controller\TaskRecurrenceController;
use Kytario\Model\Entity\User;
use Kytario\Model\Repository\StatusRepository;
use Kytario\Model\Repository\WorkflowRepository;
use Kytario\Tests\Support\Fixture;
use Kytario\Tests\Support\IntegrationTestCase;

#[CoversClass(TaskRecurrenceController::class)]
final class TaskRecurrenceControllerTest extends IntegrationTestCase
{
	public function testSetGetAndClear(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$taskId = $this->createTask($owner, $project->id, 'Weekly review');

		$empty = $this->request('GET', '/api/tasks/' . $taskId . '/recurrence', authenticatedAs: $owner);
		self::assertSame(200, $empty->getStatusCode());
		self::assertSame('null', trim((string) $empty->getBody()));

		$set = $this->request(
			'PUT',
			'/api/tasks/' . $taskId . '/recurrence',
			body: ['cadence' => 'Weekly', 'interval' => 2, 'weekday' => 1, 'endType' => 'Never'],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $set->getStatusCode());
		$dto = $this->jsonBody($set);
		self::assertSame('Weekly', $dto['cadence']);
		self::assertSame(2, $dto['interval']);
		self::assertSame(1, $dto['weekday']);
		self::assertTrue($dto['active']);
		self::assertNotNull($dto['nextRunAt']);

		$get = $this->request('GET', '/api/tasks/' . $taskId . '/recurrence', authenticatedAs: $owner);
		self::assertSame('Weekly', $this->jsonBody($get)['cadence']);

		$clear = $this->request('DELETE', '/api/tasks/' . $taskId . '/recurrence', authenticatedAs: $owner);
		self::assertSame(200, $clear->getStatusCode());

		$after = $this->request('GET', '/api/tasks/' . $taskId . '/recurrence', authenticatedAs: $owner);
		self::assertSame('null', trim((string) $after->getBody()));
	}

	public function testInvalidCronIsRejected(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$taskId = $this->createTask($owner, $project->id, 'Custom schedule');

		$response = $this->request(
			'PUT',
			'/api/tasks/' . $taskId . '/recurrence',
			body: ['cadence' => 'Cron', 'cronExpression' => 'not a cron', 'endType' => 'Never'],
			authenticatedAs: $owner,
		);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testOverlongIntervalIsRejected(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$taskId = $this->createTask($owner, $project->id, 'Pathological interval');

		$response = $this->request(
			'PUT',
			'/api/tasks/' . $taskId . '/recurrence',
			body: ['cadence' => 'Daily', 'interval' => 1001, 'endType' => 'Never'],
			authenticatedAs: $owner,
		);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testOverlongMaxOccurrencesIsRejected(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$taskId = $this->createTask($owner, $project->id, 'Pathological count');

		$response = $this->request(
			'PUT',
			'/api/tasks/' . $taskId . '/recurrence',
			body: ['cadence' => 'Daily', 'interval' => 1, 'endType' => 'AfterCount', 'maxOccurrences' => 10001],
			authenticatedAs: $owner,
		);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testOnDateWithoutEndDateIsRejected(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$taskId = $this->createTask($owner, $project->id, 'Bounded');

		$response = $this->request(
			'PUT',
			'/api/tasks/' . $taskId . '/recurrence',
			body: ['cadence' => 'Daily', 'interval' => 1, 'endType' => 'OnDate'],
			authenticatedAs: $owner,
		);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testNonMemberGetsNotFound(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$taskId = $this->createTask($owner, $project->id, 'Private task');

		$outsider = Fixture::createUser('outsider@example.com');

		$response = $this->request(
			'PUT',
			'/api/tasks/' . $taskId . '/recurrence',
			body: ['cadence' => 'Daily', 'interval' => 1, 'endType' => 'Never'],
			authenticatedAs: $outsider,
		);

		self::assertSame(404, $response->getStatusCode());
	}

	private function createTask(User $author, int $projectId, string $name): int
	{
		$response = $this->request(
			'POST',
			'/api/projects/' . $projectId . '/tasks',
			body: ['statusId' => $this->firstStatusId($projectId), 'name' => $name, 'description' => null, 'priority' => 'Medium'],
			authenticatedAs: $author,
		);

		return self::intField($this->jsonBody($response)['id']);
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
