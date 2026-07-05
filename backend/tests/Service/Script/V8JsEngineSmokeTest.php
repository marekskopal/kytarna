<?php

declare(strict_types=1);

namespace Ukolio\Tests\Service\Script;

use PHPUnit\Framework\Attributes\CoversNothing;
use Ukolio\Model\Entity\Enum\ScriptRunStatusEnum;
use Ukolio\Model\Entity\Enum\ScriptTriggerEnum;
use Ukolio\Service\Script\Engine\ScriptEngineInterface;
use Ukolio\Service\Script\Host\ScriptHostApiFactory;
use Ukolio\Service\Script\Host\ScriptRunContext;
use Ukolio\Tests\Support\AppHarness;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

/**
 * Live execution smoke test for the V8 sandbox. Only runs where ext-v8js is loaded (the
 * script-worker image) — FrankenPHP and the standard PHPUnit run do not load it, so this is
 * skipped there rather than failing.
 */
#[CoversNothing]
final class V8JsEngineSmokeTest extends IntegrationTestCase
{
	public function testRunsTrivialScriptAndCapturesLog(): void
	{
		if (!extension_loaded('v8js')) {
			self::markTestSkipped('ext-v8js is not loaded in this runtime (expected outside the script-worker image).');
		}

		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);

		$factory = AppHarness::container()->get(ScriptHostApiFactory::class);
		assert($factory instanceof ScriptHostApiFactory);
		$engine = AppHarness::container()->get(ScriptEngineInterface::class);
		assert($engine instanceof ScriptEngineInterface);

		$context = new ScriptRunContext(owner: $user, workspace: $workspace, triggerType: ScriptTriggerEnum::Manual);
		$hostApi = $factory->create($context);

		$result = $engine->execute('ukolio.log("hi from v8");', $hostApi, 5000, 67108864);

		self::assertSame(ScriptRunStatusEnum::Success, $result->status, $result->error ?? '');
		self::assertStringContainsString('hi from v8', $context->getLogs());
	}
}
