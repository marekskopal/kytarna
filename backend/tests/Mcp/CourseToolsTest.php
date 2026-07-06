<?php

declare(strict_types=1);

namespace Kytarna\Tests\Mcp;

use Kytarna\Mcp\McpUserContextInterface;
use Kytarna\Mcp\Tool\CourseTools;
use Kytarna\Model\Entity\User;
use Kytarna\Service\Actor\ActorContextInterface;
use Kytarna\Tests\Support\AppHarness;
use Kytarna\Tests\Support\Fixture;
use Kytarna\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CourseTools::class)]
final class CourseToolsTest extends IntegrationTestCase
{
	public function testListAndCreateCoursesViaMcp(): void
	{
		$user = Fixture::createUser();
		Fixture::createWorkspace($user);

		$tools = $this->bootMcpAs($user);

		self::assertCount(0, $tools->listCourses()->courses);

		$created = $tools->createCourse('Alpha', 'desc');
		self::assertSame('Alpha', $created->name);

		$list = $tools->listCourses();
		self::assertCount(1, $list->courses);
		self::assertSame('Alpha', $list->courses[0]->name);
	}

	public function testFindCourseByNameIsCaseInsensitive(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		Fixture::createCourse($user, $workspace, 'Apollo');

		$tools = $this->bootMcpAs($user);

		self::assertNotNull($tools->findCourseByName('apollo'));
		self::assertNull($tools->findCourseByName('zenith'));
	}

	public function testGetCourseThrowsWhenNotFound(): void
	{
		$user = Fixture::createUser();
		Fixture::createWorkspace($user);

		$tools = $this->bootMcpAs($user);

		$this->expectException(\RuntimeException::class);
		$tools->getCourse(9999);
	}

	public function testDeleteCourseRemovesIt(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);

		$tools = $this->bootMcpAs($user);
		$tools->deleteCourse($course->id);

		self::assertCount(0, $tools->listCourses()->courses);
	}

	private function bootMcpAs(User $user): CourseTools
	{
		$ctx = AppHarness::container()->get(McpUserContextInterface::class);
		assert($ctx instanceof McpUserContextInterface);
		$ctx->setUser($user);

		// Flip ActorContext to Agent so lectures/events get marked agent-created.
		$actor = AppHarness::container()->get(ActorContextInterface::class);
		assert($actor instanceof ActorContextInterface);
		$actor->setAgent('test-client', 'Test Client');

		$tools = AppHarness::container()->get(CourseTools::class);
		assert($tools instanceof CourseTools);
		return $tools;
	}
}
