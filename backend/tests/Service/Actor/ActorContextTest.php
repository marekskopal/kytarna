<?php

declare(strict_types=1);

namespace Kytario\Tests\Service\Actor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Kytario\Model\Entity\Enum\ActorTypeEnum;
use Kytario\Service\Actor\ActorContext;

#[CoversClass(ActorContext::class)]
final class ActorContextTest extends TestCase
{
	public function testDefaultIsHuman(): void
	{
		$ctx = new ActorContext();

		self::assertFalse($ctx->isAgent());
		self::assertSame(ActorTypeEnum::Human, $ctx->getActorType());
		self::assertNull($ctx->getMcpClientId());
		self::assertNull($ctx->getMcpClientName());
	}

	public function testSetAgentRecordsClientInfo(): void
	{
		$ctx = new ActorContext();
		$ctx->setAgent('client-abc', 'Claude');

		self::assertTrue($ctx->isAgent());
		self::assertSame(ActorTypeEnum::Agent, $ctx->getActorType());
		self::assertSame('client-abc', $ctx->getMcpClientId());
		self::assertSame('Claude', $ctx->getMcpClientName());
	}

	public function testSetHumanResetsClientInfo(): void
	{
		$ctx = new ActorContext();
		$ctx->setAgent('client-abc', 'Claude');
		$ctx->setHuman();

		self::assertFalse($ctx->isAgent());
		self::assertNull($ctx->getMcpClientId());
		self::assertNull($ctx->getMcpClientName());
	}
}
