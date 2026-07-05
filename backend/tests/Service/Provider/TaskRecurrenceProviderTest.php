<?php

declare(strict_types=1);

namespace Ukolio\Tests\Service\Provider;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use Ukolio\Model\Entity\Enum\RecurrenceCadenceEnum;
use Ukolio\Model\Entity\Enum\RecurrenceEndTypeEnum;
use Ukolio\Model\Entity\Enum\StatusTypeEnum;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\StatusRepository;
use Ukolio\Model\Repository\WorkflowRepository;
use Ukolio\Service\Provider\TaskChecklistProviderInterface;
use Ukolio\Service\Provider\TaskProviderInterface;
use Ukolio\Service\Provider\TaskRecurrenceProvider;
use Ukolio\Service\Provider\TaskRecurrenceProviderInterface;
use Ukolio\Service\Recurrence\RecurrenceConfig;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(TaskRecurrenceProvider::class)]
final class TaskRecurrenceProviderTest extends IntegrationTestCase
{
	public function testSpawnNextCarriesContentChecklistAndAdvancesSeries(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$carrier = $this->createTaskEntity($owner, $project->id, 'Water the plants', '2026-06-01');

		$checklistProvider = $this->checklistProvider();
		$checklistProvider->createItem($carrier, 'Fill the can');
		$item = $checklistProvider->createItem($carrier, 'Check the soil');
		$checklistProvider->setChecked($item, $owner, true);

		$provider = $this->recurrenceProvider();
		$recurrence = $provider->set($owner, $carrier, new RecurrenceConfig(
			cadence: RecurrenceCadenceEnum::Daily,
			interval: 3,
			endType: RecurrenceEndTypeEnum::Never,
			anchorDate: new DateTimeImmutable('2026-06-01'),
		));

		self::assertTrue($recurrence->active);
		// Scheduling is anchored to the real "now", so capture the computed date rather than hard-coding it.
		$occurrence = $recurrence->nextRunAt;
		self::assertNotNull($occurrence);

		$spawned = $provider->spawnNext($recurrence);

		self::assertInstanceOf(Task::class, $spawned);
		self::assertSame('Water the plants', $spawned->name);
		self::assertSame($occurrence->format('Y-m-d'), $spawned->dueDate?->format('Y-m-d'));
		self::assertSame(StatusTypeEnum::Start, $spawned->status->type);

		// Checklist is copied and reset to unchecked.
		$copied = $checklistProvider->findByTask($spawned);
		self::assertCount(2, $copied);
		foreach ($copied as $copiedItem) {
			self::assertNull($copiedItem->checkedAt);
		}

		// The series re-points to the new carrier and advances by the interval (3 days).
		self::assertSame($spawned->id, $recurrence->task->id);
		self::assertSame(1, $recurrence->occurrenceCount);
		self::assertSame(
			$occurrence->modify('+3 days')->format('Y-m-d'),
			$recurrence->nextRunAt?->format('Y-m-d'),
		);
	}

	public function testAfterCountTerminatesTheSeries(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$carrier = $this->createTaskEntity($owner, $project->id, 'Standup', '2026-06-01');

		$provider = $this->recurrenceProvider();
		$recurrence = $provider->set($owner, $carrier, new RecurrenceConfig(
			cadence: RecurrenceCadenceEnum::Daily,
			interval: 1,
			endType: RecurrenceEndTypeEnum::AfterCount,
			// original + 1 spawn
			maxOccurrences: 2,
			anchorDate: new DateTimeImmutable('2026-06-01'),
		));

		$first = $provider->spawnNext($recurrence);
		self::assertInstanceOf(Task::class, $first);
		self::assertFalse($recurrence->active);
		self::assertNull($recurrence->nextRunAt);

		// A second attempt is a no-op once the series has ended.
		self::assertNull($provider->spawnNext($recurrence));
	}

	private function createTaskEntity(User $author, int $projectId, string $name, string $dueDate): Task
	{
		$response = $this->request(
			'POST',
			'/api/projects/' . $projectId . '/tasks',
			body: [
				'statusId' => $this->firstStatusId(
					$projectId,
				),
				'name' => $name,
				'description' => null,
				'priority' => 'Medium',
				'dueDate' => $dueDate],
			authenticatedAs: $author,
		);
		$id = self::intField($this->jsonBody($response)['id']);

		$task = $this->taskProvider()->getTask($id);
		self::assertInstanceOf(Task::class, $task);

		return $task;
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
			if ($status->type === StatusTypeEnum::Start) {
				return $status->id;
			}
		}

		self::fail('Project has no Start status.');
	}

	private function recurrenceProvider(): TaskRecurrenceProviderInterface
	{
		$provider = $this->container->get(TaskRecurrenceProviderInterface::class);
		assert($provider instanceof TaskRecurrenceProviderInterface);

		return $provider;
	}

	private function taskProvider(): TaskProviderInterface
	{
		$provider = $this->container->get(TaskProviderInterface::class);
		assert($provider instanceof TaskProviderInterface);

		return $provider;
	}

	private function checklistProvider(): TaskChecklistProviderInterface
	{
		$provider = $this->container->get(TaskChecklistProviderInterface::class);
		assert($provider instanceof TaskChecklistProviderInterface);

		return $provider;
	}
}
