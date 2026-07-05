<?php

declare(strict_types=1);

namespace Ukolio\Tests\Mcp;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Ukolio\Mcp\Dto\McpPriorityDto;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Mcp\Tool\PriorityTools;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\StatusRepository;
use Ukolio\Model\Repository\WorkflowRepository;
use Ukolio\Service\Actor\ActorContextInterface;
use Ukolio\Service\Provider\PriorityProviderInterface;
use Ukolio\Service\Provider\TaskProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Tests\Support\AppHarness;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(PriorityTools::class)]
final class PriorityToolsTest extends IntegrationTestCase
{
	public function testListReturnsSeededWorkspacePriorities(): void
	{
		$owner = Fixture::createUser();
		Fixture::createWorkspace($owner);

		$tools = $this->bootAs($owner);

		$result = $tools->listWorkspacePriorities();

		$names = array_map(static fn (McpPriorityDto $p): string => $p->name, $result->priorities);
		self::assertSame(['High', 'Medium', 'Low'], $names);

		$defaults = array_filter($result->priorities, static fn (McpPriorityDto $p): bool => $p->isDefault);
		self::assertCount(1, $defaults);
		self::assertSame('Medium', array_values($defaults)[0]->name);
	}

	public function testFindByNameIsCaseInsensitive(): void
	{
		$owner = Fixture::createUser();
		Fixture::createWorkspace($owner);

		$tools = $this->bootAs($owner);

		$found = $tools->findPriorityByName('mEdIuM');
		self::assertNotNull($found);
		self::assertSame('Medium', $found->name);

		self::assertNull($tools->findPriorityByName('Critical'));
	}

	public function testFindByNameIsScopedToCurrentWorkspace(): void
	{
		$ownerA = Fixture::createUser();
		Fixture::createWorkspace($ownerA, 'A');

		$ownerB = Fixture::createUser(email: 'b@example.com');
		$workspaceB = Fixture::createWorkspace($ownerB, 'B');
		$priorityProvider = AppHarness::container()->get(PriorityProviderInterface::class);
		assert($priorityProvider instanceof PriorityProviderInterface);
		$priorityProvider->createPriority($workspaceB, 'Critical', '#dc2626', false);

		$tools = $this->bootAs($ownerA);
		self::assertNull($tools->findPriorityByName('Critical'));
	}

	public function testCreateWithoutPositionAppendsToEnd(): void
	{
		$owner = Fixture::createUser();
		Fixture::createWorkspace($owner);

		$tools = $this->bootAs($owner);

		$created = $tools->createPriority(name: 'Urgent', color: '#dc2626');

		self::assertSame('Urgent', $created->name);
		self::assertSame('#dc2626', $created->color);
		self::assertFalse($created->isDefault);

		// Listed in position order — should be last.
		$listed = $tools->listWorkspacePriorities()->priorities;
		$last = $listed[count($listed) - 1];
		self::assertSame('Urgent', $last->name);
	}

	public function testCreateWithPositionInsertsAtThatIndex(): void
	{
		$owner = Fixture::createUser();
		Fixture::createWorkspace($owner);
		$tools = $this->bootAs($owner);

		$created = $tools->createPriority(name: 'Urgent', color: '#dc2626', position: 0);
		self::assertSame(0, $created->position);

		// Verify persistence by reading the DB directly; the ORM identity map can hold a stale
		// in-memory snapshot for entities created and then immediately mutated within the same request.
		$pdo = AppHarness::pdo();
		$stmt = $pdo->query('SELECT name, position FROM priorities ORDER BY position ASC, id ASC');
		assert($stmt !== false);
		/** @var list<array{name: string, position: int|string}> $rows */
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		self::assertSame('Urgent', $rows[0]['name']);
		self::assertSame(0, (int) $rows[0]['position']);
	}

	public function testCreateWithIsDefaultClearsPreviousDefault(): void
	{
		$owner = Fixture::createUser();
		Fixture::createWorkspace($owner);

		$tools = $this->bootAs($owner);

		$tools->createPriority(name: 'Urgent', color: '#dc2626', isDefault: true);

		$listed = $tools->listWorkspacePriorities()->priorities;
		$defaults = array_values(array_filter($listed, static fn (McpPriorityDto $p): bool => $p->isDefault));
		self::assertCount(1, $defaults);
		self::assertSame('Urgent', $defaults[0]->name);
	}

	public function testUpdateOnlyPatchesProvidedFields(): void
	{
		$owner = Fixture::createUser();
		Fixture::createWorkspace($owner);
		$tools = $this->bootAs($owner);

		$medium = $tools->findPriorityByName('Medium');
		self::assertNotNull($medium);

		$updated = $tools->updatePriority(priorityId: $medium->id, color: '#123456');

		// unchanged
		self::assertSame('Medium', $updated->name);
		self::assertSame('#123456', $updated->color);
		// unchanged
		self::assertTrue($updated->isDefault);
	}

	public function testDeleteSucceedsWhenNotReferenced(): void
	{
		$owner = Fixture::createUser();
		Fixture::createWorkspace($owner);
		$tools = $this->bootAs($owner);

		$created = $tools->createPriority(name: 'Throwaway', color: '#abcdef');

		$result = $tools->deletePriority($created->id);
		self::assertSame('Priority deleted.', $result);
		self::assertNull($tools->findPriorityByName('Throwaway'));
	}

	public function testDeleteBlockedWhenTasksReferenceIt(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);

		$tools = $this->bootAs($owner);
		$medium = $tools->findPriorityByName('Medium');
		self::assertNotNull($medium);

		// Create a task using the seeded "Medium" priority.
		$priorityProvider = AppHarness::container()->get(PriorityProviderInterface::class);
		assert($priorityProvider instanceof PriorityProviderInterface);
		$priority = $priorityProvider->getPriority($workspace, $medium->id);
		self::assertNotNull($priority);

		$workflowRepo = AppHarness::container()->get(WorkflowRepository::class);
		assert($workflowRepo instanceof WorkflowRepository);
		$workflow = $workflowRepo->findByProject($project->id);
		self::assertNotNull($workflow);
		$statusRepo = AppHarness::container()->get(StatusRepository::class);
		assert($statusRepo instanceof StatusRepository);
		$statuses = iterator_to_array($statusRepo->findByWorkflow($workflow->id), false);
		self::assertNotEmpty($statuses);

		$taskProvider = AppHarness::container()->get(TaskProviderInterface::class);
		assert($taskProvider instanceof TaskProviderInterface);
		$taskProvider->createTask(
			author: $owner,
			project: $project,
			status: $statuses[0],
			name: 'Task',
			description: null,
			priority: $priority,
			dueDate: null,
		);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('1 task(s) still reference it');
		$tools->deletePriority($medium->id);
	}

	public function testMemberCannotMutate(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$member = Fixture::createUser(email: 'member@example.com');
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);

		$workspaceProvider = AppHarness::container()->get(WorkspaceProviderInterface::class);
		assert($workspaceProvider instanceof WorkspaceProviderInterface);
		$workspaceProvider->switchCurrentWorkspace($member, $workspace);

		$tools = $this->bootAs($member);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('do not have permission');
		$tools->createPriority(name: 'Spam', color: '#000000');
	}

	private function bootAs(User $user): PriorityTools
	{
		$ctx = AppHarness::container()->get(McpUserContextInterface::class);
		assert($ctx instanceof McpUserContextInterface);
		$ctx->setUser($user);

		$actor = AppHarness::container()->get(ActorContextInterface::class);
		assert($actor instanceof ActorContextInterface);
		$actor->setAgent('cli', 'Test CLI');

		$tools = AppHarness::container()->get(PriorityTools::class);
		assert($tools instanceof PriorityTools);
		return $tools;
	}
}
