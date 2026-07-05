<?php

declare(strict_types=1);

namespace Ukolio\Tests\Service\Provider;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Ukolio\Model\Entity\Enum\EventTypeEnum;
use Ukolio\Model\Entity\Enum\LocaleEnum;
use Ukolio\Model\Entity\Enum\StatusTypeEnum;
use Ukolio\Model\Entity\Enum\SystemRoleEnum;
use Ukolio\Model\Entity\Enum\TaskRelationTypeEnum;
use Ukolio\Model\Entity\Priority;
use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\Status;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskRelation;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workflow;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Service\Provider\TaskRelationProvider;
use Ukolio\Tests\Service\Provider\Fake\FakeEventProvider;
use Ukolio\Tests\Service\Provider\Fake\FakeTaskRelationRepository;

#[CoversClass(TaskRelationProvider::class)]
final class TaskRelationProviderTest extends TestCase
{
	public function testCreatesRelationAndRecordsEvent(): void
	{
		$ws = $this->makeWorkspace(1);
		$project = $this->makeProject(1, $ws);
		$source = $this->makeTask(1, $project);
		$target = $this->makeTask(2, $project);

		$repo = new FakeTaskRelationRepository();
		$events = new FakeEventProvider();
		$provider = new TaskRelationProvider($repo, $events);

		$rel = $provider->createRelation($this->makeUser(1), $source, $target, TaskRelationTypeEnum::Related);

		self::assertSame(TaskRelationTypeEnum::Related, $rel->type);
		self::assertSame($source, $rel->sourceTask);
		self::assertSame($target, $rel->targetTask);
		self::assertCount(1, $repo->stored);
		self::assertCount(1, $events->recorded);
		self::assertSame(EventTypeEnum::TaskRelationCreated, $events->recorded[0]['type']);
	}

	public function testRejectsSelfRelation(): void
	{
		$task = $this->makeTask(1, $this->makeProject(1, $this->makeWorkspace(1)));
		$provider = new TaskRelationProvider(new FakeTaskRelationRepository(), new FakeEventProvider());

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('cannot relate to itself');
		$provider->createRelation($this->makeUser(1), $task, $task, TaskRelationTypeEnum::Related);
	}

	public function testRejectsCrossWorkspaceRelation(): void
	{
		$wsA = $this->makeWorkspace(1);
		$wsB = $this->makeWorkspace(2);
		$source = $this->makeTask(1, $this->makeProject(1, $wsA));
		$target = $this->makeTask(2, $this->makeProject(2, $wsB));
		$provider = new TaskRelationProvider(new FakeTaskRelationRepository(), new FakeEventProvider());

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('same workspace');
		$provider->createRelation($this->makeUser(1), $source, $target, TaskRelationTypeEnum::Related);
	}

	public function testRejectsDuplicateRelation(): void
	{
		$ws = $this->makeWorkspace(1);
		$project = $this->makeProject(1, $ws);
		$source = $this->makeTask(1, $project);
		$target = $this->makeTask(2, $project);

		$repo = new FakeTaskRelationRepository();
		$repo->stored[] = $this->makeRelation(1, $source, $target, TaskRelationTypeEnum::DependsOn);

		$provider = new TaskRelationProvider($repo, new FakeEventProvider());

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('already exists');
		$provider->createRelation($this->makeUser(1), $source, $target, TaskRelationTypeEnum::DependsOn);
	}

	public function testRejectsInverseDuplicateForSymmetricType(): void
	{
		$ws = $this->makeWorkspace(1);
		$project = $this->makeProject(1, $ws);
		$source = $this->makeTask(1, $project);
		$target = $this->makeTask(2, $project);

		$repo = new FakeTaskRelationRepository();
		$repo->stored[] = $this->makeRelation(1, $target, $source, TaskRelationTypeEnum::Related);

		$provider = new TaskRelationProvider($repo, new FakeEventProvider());

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('already exists');
		$provider->createRelation($this->makeUser(1), $source, $target, TaskRelationTypeEnum::Related);
	}

	public function testRejectsDirectParentCycle(): void
	{
		$ws = $this->makeWorkspace(1);
		$project = $this->makeProject(1, $ws);
		$a = $this->makeTask(1, $project);
		$b = $this->makeTask(2, $project);

		$repo = new FakeTaskRelationRepository();
		$repo->stored[] = $this->makeRelation(1, $a, $b, TaskRelationTypeEnum::Parent);

		$provider = new TaskRelationProvider($repo, new FakeEventProvider());

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('cycle');
		$provider->createRelation($this->makeUser(1), $b, $a, TaskRelationTypeEnum::Parent);
	}

	public function testRejectsTransitiveParentCycle(): void
	{
		$ws = $this->makeWorkspace(1);
		$project = $this->makeProject(1, $ws);
		$a = $this->makeTask(1, $project);
		$b = $this->makeTask(2, $project);
		$c = $this->makeTask(3, $project);

		$repo = new FakeTaskRelationRepository();
		$repo->stored[] = $this->makeRelation(1, $a, $b, TaskRelationTypeEnum::Parent);
		$repo->stored[] = $this->makeRelation(2, $b, $c, TaskRelationTypeEnum::Parent);

		$provider = new TaskRelationProvider($repo, new FakeEventProvider());

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('cycle');
		$provider->createRelation($this->makeUser(1), $c, $a, TaskRelationTypeEnum::Parent);
	}

	public function testRejectsTransitiveDependsOnCycle(): void
	{
		$ws = $this->makeWorkspace(1);
		$project = $this->makeProject(1, $ws);
		$a = $this->makeTask(1, $project);
		$b = $this->makeTask(2, $project);
		$c = $this->makeTask(3, $project);

		$repo = new FakeTaskRelationRepository();
		$repo->stored[] = $this->makeRelation(1, $a, $b, TaskRelationTypeEnum::DependsOn);
		$repo->stored[] = $this->makeRelation(2, $b, $c, TaskRelationTypeEnum::DependsOn);

		$provider = new TaskRelationProvider($repo, new FakeEventProvider());

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('cycle');
		$provider->createRelation($this->makeUser(1), $c, $a, TaskRelationTypeEnum::DependsOn);
	}

	public function testParentGraphDoesNotBlockDependsOn(): void
	{
		$ws = $this->makeWorkspace(1);
		$project = $this->makeProject(1, $ws);
		$a = $this->makeTask(1, $project);
		$b = $this->makeTask(2, $project);

		$repo = new FakeTaskRelationRepository();
		$repo->stored[] = $this->makeRelation(1, $a, $b, TaskRelationTypeEnum::Parent);

		$provider = new TaskRelationProvider($repo, new FakeEventProvider());

		$rel = $provider->createRelation($this->makeUser(1), $b, $a, TaskRelationTypeEnum::DependsOn);
		self::assertSame(TaskRelationTypeEnum::DependsOn, $rel->type);
	}

	public function testRelatedSkipsCycleCheck(): void
	{
		$ws = $this->makeWorkspace(1);
		$project = $this->makeProject(1, $ws);
		$a = $this->makeTask(1, $project);
		$b = $this->makeTask(2, $project);
		$c = $this->makeTask(3, $project);

		$repo = new FakeTaskRelationRepository();
		$repo->stored[] = $this->makeRelation(1, $a, $b, TaskRelationTypeEnum::Related);
		$repo->stored[] = $this->makeRelation(2, $b, $c, TaskRelationTypeEnum::Related);

		$provider = new TaskRelationProvider($repo, new FakeEventProvider());

		$rel = $provider->createRelation($this->makeUser(1), $c, $a, TaskRelationTypeEnum::Related);
		self::assertSame(TaskRelationTypeEnum::Related, $rel->type);
	}

	public function testDeleteAllForTaskRemovesOutgoingAndIncoming(): void
	{
		$ws = $this->makeWorkspace(1);
		$project = $this->makeProject(1, $ws);
		$a = $this->makeTask(1, $project);
		$b = $this->makeTask(2, $project);
		$c = $this->makeTask(3, $project);

		$repo = new FakeTaskRelationRepository();
		$repo->stored[] = $this->makeRelation(1, $a, $b, TaskRelationTypeEnum::Related);
		$repo->stored[] = $this->makeRelation(2, $c, $a, TaskRelationTypeEnum::DependsOn);
		$repo->stored[] = $this->makeRelation(3, $b, $c, TaskRelationTypeEnum::Related);

		$provider = new TaskRelationProvider($repo, new FakeEventProvider());
		$provider->deleteAllForTask($a);

		self::assertCount(1, $repo->stored);
		self::assertSame(3, $repo->stored[0]->id);
	}

	private function makeUser(int $id): User
	{
		$user = new User(
			email: sprintf('u%d@example.com', $id),
			password: 'x',
			name: 'User ' . $id,
			locale: LocaleEnum::En,
			currentWorkspaceId: null,
			systemRole: SystemRoleEnum::User,
		);
		$user->id = $id;
		$user->createdAt = new DateTimeImmutable();
		$user->updatedAt = new DateTimeImmutable();
		return $user;
	}

	private function makeWorkspace(int $id): Workspace
	{
		$ws = new Workspace(owner: $this->makeUser(100 + $id), name: 'WS-' . $id);
		$ws->id = $id;
		$ws->createdAt = new DateTimeImmutable();
		$ws->updatedAt = new DateTimeImmutable();
		return $ws;
	}

	private function makeProject(int $id, Workspace $workspace): Project
	{
		$p = new Project(workspace: $workspace, name: 'P-' . $id, prefix: 'P' . $id);
		$p->id = $id;
		$p->createdAt = new DateTimeImmutable();
		$p->updatedAt = new DateTimeImmutable();
		return $p;
	}

	private function makeTask(int $id, Project $project): Task
	{
		$workflow = new Workflow(project: $project, name: 'wf');
		$workflow->id = $id * 100;
		$workflow->createdAt = new DateTimeImmutable();
		$workflow->updatedAt = new DateTimeImmutable();

		$status = new Status(workflow: $workflow, name: 'To Do', color: '#888888', position: 0, type: StatusTypeEnum::Start);
		$status->id = $id * 10;
		$status->createdAt = new DateTimeImmutable();
		$status->updatedAt = new DateTimeImmutable();

		$priority = new Priority(workspace: $project->workspace, name: 'Medium', color: '#fbf2dd', position: 1, isDefault: true);
		$priority->id = $id * 1000;
		$priority->createdAt = new DateTimeImmutable();
		$priority->updatedAt = new DateTimeImmutable();

		$task = new Task(
			project: $project,
			status: $status,
			assignee: null,
			name: 'Task ' . $id,
			description: null,
			priority: $priority,
			dueDate: null,
			position: 0,
			sequenceNumber: $id,
		);
		$task->id = $id;
		$task->createdAt = new DateTimeImmutable();
		$task->updatedAt = new DateTimeImmutable();
		return $task;
	}

	private function makeRelation(int $id, Task $source, Task $target, TaskRelationTypeEnum $type): TaskRelation
	{
		$rel = new TaskRelation(sourceTask: $source, targetTask: $target, type: $type, createdBy: null);
		$rel->id = $id;
		$rel->createdAt = new DateTimeImmutable();
		$rel->updatedAt = new DateTimeImmutable();
		return $rel;
	}
}
