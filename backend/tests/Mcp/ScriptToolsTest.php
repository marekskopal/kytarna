<?php

declare(strict_types=1);

namespace Ukolio\Tests\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Ukolio\Dto\ScriptDto;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Mcp\Tool\ScriptTools;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Model\Entity\User;
use Ukolio\Service\Actor\ActorContextInterface;
use Ukolio\Tests\Support\AppHarness;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(ScriptTools::class)]
final class ScriptToolsTest extends IntegrationTestCase
{
	public function testCreateListGetUpdateDeleteScheduledScript(): void
	{
		$user = Fixture::createUser();
		Fixture::createWorkspace($user);

		$tools = $this->bootAs($user);

		$created = $tools->createScript(
			name: 'Archive stale Done',
			source: 'ukolio.log("hi");',
			trigger: 'Scheduled',
			triggerConfig: '0 3 * * *',
		);
		self::assertSame('Scheduled', $created->trigger);
		self::assertSame('0 3 * * *', $created->triggerConfig);
		self::assertTrue($created->active);

		$listed = $tools->listScripts()->scripts;
		self::assertCount(1, $listed);

		$fetched = $tools->getScript($created->id);
		self::assertSame('ukolio.log("hi");', $fetched->source);

		$updated = $tools->updateScript($created->id, active: false);
		self::assertFalse($updated->active);
		// unchanged
		self::assertSame('Archive stale Done', $updated->name);
		// unchanged
		self::assertSame('0 3 * * *', $updated->triggerConfig);

		self::assertSame('Script deleted.', $tools->deleteScript($created->id));
		$remainingIds = array_map(static fn (ScriptDto $s): int => $s->id, $tools->listScripts()->scripts);
		self::assertNotContains($created->id, $remainingIds);
	}

	public function testInvalidTriggerThrows(): void
	{
		$user = Fixture::createUser();
		Fixture::createWorkspace($user);

		$tools = $this->bootAs($user);

		$this->expectException(RuntimeException::class);
		$tools->createScript(name: 'Bad', source: 'ukolio.log(1);', trigger: 'Whenever');
	}

	public function testMemberCannotCreateScript(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$member = Fixture::createUser('member@example.com');
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);

		$tools = $this->bootAs($member);

		$this->expectException(RuntimeException::class);
		$tools->createScript(name: 'Nope', source: 'ukolio.log(1);', trigger: 'Manual');
	}

	public function testMemberCannotListScriptRuns(): void
	{
		// Run logs/errors may carry secret-variable values — agents acting as a plain Member are denied.
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$script = $this->bootAs($owner)->createScript(name: 'Digest', source: 'ukolio.log(1);', trigger: 'Manual');

		$member = Fixture::createUser('member@example.com');
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);

		$this->expectException(RuntimeException::class);
		$this->bootAs($member)->listScriptRuns($script->id);
	}

	private function bootAs(User $user): ScriptTools
	{
		$ctx = AppHarness::container()->get(McpUserContextInterface::class);
		assert($ctx instanceof McpUserContextInterface);
		$ctx->setUser($user);

		$actor = AppHarness::container()->get(ActorContextInterface::class);
		assert($actor instanceof ActorContextInterface);
		$actor->setAgent('cli', 'Test CLI');

		$tools = AppHarness::container()->get(ScriptTools::class);
		assert($tools instanceof ScriptTools);

		return $tools;
	}
}
