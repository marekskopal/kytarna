<?php

declare(strict_types=1);

namespace Kytarna\Tests\Service\Actor;

use Kytarna\Model\Entity\Enum\ActorTypeEnum;
use Kytarna\Service\Actor\ActorContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

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
