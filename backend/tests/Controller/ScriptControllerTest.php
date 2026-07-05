<?php

declare(strict_types=1);

namespace Ukolio\Tests\Controller;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use Ukolio\Controller\ScriptController;
use Ukolio\Model\Entity\Enum\EventTypeEnum;
use Ukolio\Model\Entity\Enum\ScriptRunStatusEnum;
use Ukolio\Model\Entity\Enum\ScriptTriggerEnum;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Model\Entity\Script;
use Ukolio\Model\Entity\ScriptRun;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\ScriptRunRepository;
use Ukolio\Service\Provider\EventProviderInterface;
use Ukolio\Service\Script\ScriptProviderInterface;
use Ukolio\Tests\Support\AppHarness;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(ScriptController::class)]
final class ScriptControllerTest extends IntegrationTestCase
{
	public function testListReturnsRunCountAndLastStatus(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$script = $this->createScript($user, $workspace);

		$this->persistRun($script, ScriptRunStatusEnum::Success, 1, 3);
		$this->persistRun($script, ScriptRunStatusEnum::Error, 2, 7);

		$response = $this->request('GET', '/api/workspaces/' . $workspace->id . '/scripts', null, $user);
		self::assertSame(200, $response->getStatusCode());

		$scripts = $this->jsonList($response);
		self::assertCount(1, $scripts);
		self::assertSame(2, $scripts[0]['runCount']);
		// Latest persisted run wins.
		self::assertSame('Error', $scripts[0]['lastStatus']);
	}

	public function testRunsEndpointExposesCallCounts(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$script = $this->createScript($user, $workspace);

		$this->persistRun($script, ScriptRunStatusEnum::Success, 5, 42);

		$response = $this->request('GET', '/api/scripts/' . $script->id . '/runs', null, $user);
		self::assertSame(200, $response->getStatusCode());

		$runs = $this->jsonList($response);
		self::assertCount(1, $runs);
		self::assertSame(5, $runs[0]['httpCalls']);
		self::assertSame(42, $runs[0]['taskApiCalls']);
	}

	public function testMemberCannotReadRunHistory(): void
	{
		// Run logs/errors may contain secret-variable values, so only script managers may read them.
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$member = Fixture::createUser('member@example.com');
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);
		$script = $this->createScript($owner, $workspace);
		$this->persistRun($script, ScriptRunStatusEnum::Success, 1, 1);

		$response = $this->request('GET', '/api/scripts/' . $script->id . '/runs', null, $member);
		self::assertSame(401, $response->getStatusCode());
	}

	public function testMemberCannotCreateScript(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$member = Fixture::createUser('member@example.com');
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);

		$body = ['name' => 'Nope', 'source' => 'ukolio.log(1);', 'trigger' => 'Manual', 'triggerConfig' => null, 'active' => true];
		$response = $this->request('POST', '/api/workspaces/' . $workspace->id . '/scripts', $body, $member);

		self::assertSame(401, $response->getStatusCode());
	}

	public function testCreateAndDeleteRecordScriptEvents(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);

		$body = ['name' => 'Digest', 'source' => 'ukolio.log(1);', 'trigger' => 'Manual', 'triggerConfig' => null, 'active' => true];
		$created = $this->request('POST', '/api/workspaces/' . $workspace->id . '/scripts', $body, $owner);
		self::assertSame(200, $created->getStatusCode());
		self::assertSame(1, $this->countEvents($workspace, EventTypeEnum::ScriptCreated));

		$scriptId = self::intField($this->jsonBody($created)['id']);
		$deleted = $this->request('DELETE', '/api/scripts/' . $scriptId, null, $owner);
		self::assertSame(200, $deleted->getStatusCode());
		self::assertSame(1, $this->countEvents($workspace, EventTypeEnum::ScriptDeleted));
	}

	private function countEvents(Workspace $workspace, EventTypeEnum $type): int
	{
		$eventProvider = AppHarness::container()->get(EventProviderInterface::class);
		assert($eventProvider instanceof EventProviderInterface);

		return count(iterator_to_array($eventProvider->getWorkspaceEventsFiltered($workspace, null, null, $type, 50, 0), false));
	}

	private function createScript(User $user, Workspace $workspace): Script
	{
		$provider = AppHarness::container()->get(ScriptProviderInterface::class);
		assert($provider instanceof ScriptProviderInterface);

		return $provider->create($user, $workspace, 'Digest', 'ukolio.log("hi");', ScriptTriggerEnum::Manual, null, true);
	}

	private function persistRun(Script $script, ScriptRunStatusEnum $status, int $httpCalls, int $taskApiCalls): void
	{
		$now = new DateTimeImmutable();
		$run = new ScriptRun(
			script: $script,
			triggerType: ScriptTriggerEnum::Manual,
			status: $status,
			startedAt: $now,
			finishedAt: $now,
			httpCalls: $httpCalls,
			taskApiCalls: $taskApiCalls,
		);
		$run->createdAt = $now;
		$run->updatedAt = $now;

		$repository = AppHarness::container()->get(ScriptRunRepository::class);
		assert($repository instanceof ScriptRunRepository);
		$repository->persist($run);
	}
}
