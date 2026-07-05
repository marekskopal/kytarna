<?php

declare(strict_types=1);

namespace Ukolio\Tests\Service\Script;

use Ukolio\Model\Entity\Enum\ScriptRunStatusEnum;
use Ukolio\Model\Entity\Enum\ScriptTriggerEnum;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Script\ScriptProviderInterface;
use Ukolio\Service\Script\ScriptRunner;
use Ukolio\Tests\Support\AppHarness;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

final class ScriptRunnerOwnerTest extends IntegrationTestCase
{
	public function testRunIsSkippedWhenOwnerNoLongerBelongsToWorkspace(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);

		// An admin authors a script, then is removed from the workspace.
		$admin = Fixture::createUser();
		Fixture::addMember($workspace, $admin, WorkspaceRoleEnum::Admin);

		$scriptProvider = AppHarness::container()->get(ScriptProviderInterface::class);
		assert($scriptProvider instanceof ScriptProviderInterface);
		$script = $scriptProvider->create($admin, $workspace, 'Digest', 'ukolio.log("hi");', ScriptTriggerEnum::Manual, null, true);

		$workspaceProvider = AppHarness::container()->get(WorkspaceProviderInterface::class);
		assert($workspaceProvider instanceof WorkspaceProviderInterface);
		$membership = $workspaceProvider->findMembership($admin, $workspace);
		assert($membership !== null);
		$workspaceProvider->removeMember($membership);

		$runner = AppHarness::container()->get(ScriptRunner::class);
		assert($runner instanceof ScriptRunner);
		$run = $runner->run($script, ScriptTriggerEnum::Manual);

		self::assertSame(ScriptRunStatusEnum::Error, $run->status);
		self::assertNotNull($run->error);
		self::assertStringContainsString('no longer a member', $run->error);
	}
}
