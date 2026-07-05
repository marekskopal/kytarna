<?php

declare(strict_types=1);

namespace Kytario\Tests\Service\Script;

use Kytario\Model\Entity\Enum\ScriptRunStatusEnum;
use Kytario\Model\Entity\Enum\ScriptTriggerEnum;
use Kytario\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use Kytario\Service\Script\ScriptProviderInterface;
use Kytario\Service\Script\ScriptRunner;
use Kytario\Tests\Support\AppHarness;
use Kytario\Tests\Support\Fixture;
use Kytario\Tests\Support\IntegrationTestCase;

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
		$script = $scriptProvider->create($admin, $workspace, 'Digest', 'kytario.log("hi");', ScriptTriggerEnum::Manual, null, true);

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
