<?php

declare(strict_types=1);

namespace Kytario\Tests\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use Kytario\Mcp\McpUserContextInterface;
use Kytario\Mcp\Tool\ProjectTools;
use Kytario\Model\Entity\User;
use Kytario\Service\Actor\ActorContextInterface;
use Kytario\Tests\Support\AppHarness;
use Kytario\Tests\Support\Fixture;
use Kytario\Tests\Support\IntegrationTestCase;

#[CoversClass(ProjectTools::class)]
final class ProjectToolsTest extends IntegrationTestCase
{
	public function testListAndCreateProjectsViaMcp(): void
	{
		$user = Fixture::createUser();
		Fixture::createWorkspace($user);

		$tools = $this->bootMcpAs($user);

		self::assertCount(0, $tools->listProjects()->projects);

		$created = $tools->createProject('Alpha', 'desc');
		self::assertSame('Alpha', $created->name);

		$list = $tools->listProjects();
		self::assertCount(1, $list->projects);
		self::assertSame('Alpha', $list->projects[0]->name);
	}

	public function testFindProjectByNameIsCaseInsensitive(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		Fixture::createProject($user, $workspace, 'Apollo');

		$tools = $this->bootMcpAs($user);

		self::assertNotNull($tools->findProjectByName('apollo'));
		self::assertNull($tools->findProjectByName('zenith'));
	}

	public function testGetProjectThrowsWhenNotFound(): void
	{
		$user = Fixture::createUser();
		Fixture::createWorkspace($user);

		$tools = $this->bootMcpAs($user);

		$this->expectException(\RuntimeException::class);
		$tools->getProject(9999);
	}

	public function testDeleteProjectRemovesIt(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		$tools = $this->bootMcpAs($user);
		$tools->deleteProject($project->id);

		self::assertCount(0, $tools->listProjects()->projects);
	}

	private function bootMcpAs(User $user): ProjectTools
	{
		$ctx = AppHarness::container()->get(McpUserContextInterface::class);
		assert($ctx instanceof McpUserContextInterface);
		$ctx->setUser($user);

		// Flip ActorContext to Agent so tasks/events get marked agent-created.
		$actor = AppHarness::container()->get(ActorContextInterface::class);
		assert($actor instanceof ActorContextInterface);
		$actor->setAgent('test-client', 'Test Client');

		$tools = AppHarness::container()->get(ProjectTools::class);
		assert($tools instanceof ProjectTools);
		return $tools;
	}
}
