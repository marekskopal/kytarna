<?php

declare(strict_types=1);

namespace Kytario\Tests\Service\Script;

use PHPUnit\Framework\Attributes\CoversClass;
use Kytario\Model\Entity\Enum\ScriptTriggerEnum;
use Kytario\Service\Script\Host\ScriptRunContext;
use Kytario\Tests\Support\Fixture;
use Kytario\Tests\Support\IntegrationTestCase;

#[CoversClass(ScriptRunContext::class)]
final class ScriptRunContextTest extends IntegrationTestCase
{
	public function testRedactsRegisteredSecretsFromLogsAndArbitraryText(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);

		$context = new ScriptRunContext($user, $workspace, ScriptTriggerEnum::Manual);
		$context->registerSecret('super-secret-token');

		// Error messages (and anything else leaving the run) are scrubbed, not just logs.
		$redacted = $context->redactSecrets('fetch failed for https://api/?key=super-secret-token');
		self::assertStringNotContainsString('super-secret-token', $redacted);

		$context->log('used token super-secret-token');
		self::assertStringNotContainsString('super-secret-token', $context->getLogs());
	}

	public function testEmptySecretIsIgnored(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);

		$context = new ScriptRunContext($user, $workspace, ScriptTriggerEnum::Manual);
		$context->registerSecret('');

		self::assertSame('nothing to redact', $context->redactSecrets('nothing to redact'));
	}
}
