<?php

declare(strict_types=1);

namespace Kytario\Tests\Service\Realtime;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Kytario\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use Kytario\Service\Realtime\MercureCookieIssuer;
use Kytario\Service\Realtime\RealtimePublisher;
use Kytario\Tests\Support\Fixture;
use Kytario\Tests\Support\IntegrationTestCase;

#[CoversClass(MercureCookieIssuer::class)]
final class MercureCookieIssuerTest extends IntegrationTestCase
{
	private const string Key = 'integration-test-key-0000000000000000';

	public function testCookieListsEveryWorkspaceTheUserBelongsTo(): void
	{
		$owner = Fixture::createUser();
		$workspaceA = Fixture::createWorkspace($owner, 'WS A');
		$workspaceB = Fixture::createWorkspace($owner, 'WS B');

		$other = Fixture::createUser();
		$thirdWorkspace = Fixture::createWorkspace($other, 'WS C');
		Fixture::addMember($thirdWorkspace, $owner, WorkspaceRoleEnum::Member);

		$workspaceProvider = $this->container->get(WorkspaceProviderInterface::class);
		assert($workspaceProvider instanceof WorkspaceProviderInterface);

		$issuer = new MercureCookieIssuer($workspaceProvider, self::Key);

		$cookie = $issuer->issue($owner, secure: false);

		self::assertStringStartsWith('mercureAuthorization=', $cookie);
		self::assertStringContainsString('; Path=/.well-known/mercure', $cookie);
		self::assertStringContainsString('; HttpOnly', $cookie);
		self::assertStringContainsString('; SameSite=Strict', $cookie);
		self::assertStringNotContainsString('; Secure', $cookie);

		$jwt = $this->extractCookieValue($cookie);
		$decoded = JWT::decode($jwt, new Key(self::Key, 'HS256'));

		$mercure = $decoded->mercure ?? null;
		self::assertInstanceOf(stdClass::class, $mercure);

		$subscribe = $mercure->subscribe ?? null;
		self::assertIsArray($subscribe);

		/** @var list<string> $subscribed */
		$subscribed = array_values($subscribe);
		sort($subscribed);

		$expected = [
			RealtimePublisher::UserTopicPrefix . $owner->id,
			RealtimePublisher::TopicPrefix . $workspaceA->id,
			RealtimePublisher::TopicPrefix . $workspaceB->id,
			RealtimePublisher::TopicPrefix . $thirdWorkspace->id,
		];
		sort($expected);

		self::assertSame($expected, $subscribed);
	}

	public function testClearCookieExpiresInPast(): void
	{
		$workspaceProvider = $this->container->get(WorkspaceProviderInterface::class);
		assert($workspaceProvider instanceof WorkspaceProviderInterface);

		$issuer = new MercureCookieIssuer($workspaceProvider, self::Key);

		$cookie = $issuer->clear(secure: true);

		self::assertStringStartsWith('mercureAuthorization=;', $cookie);
		self::assertStringContainsString('; Max-Age=0', $cookie);
		self::assertStringContainsString('; Expires=Thu, 01 Jan 1970 00:00:00 GMT', $cookie);
		self::assertStringContainsString('; Secure', $cookie);
	}

	private function extractCookieValue(string $cookieHeader): string
	{
		$first = explode(';', $cookieHeader, 2)[0];
		return substr($first, strlen('mercureAuthorization='));
	}
}
