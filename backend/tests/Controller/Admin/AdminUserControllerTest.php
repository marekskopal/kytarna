<?php

declare(strict_types=1);

namespace Kytario\Tests\Controller\Admin;

use Kytario\Controller\Admin\AdminUserController;
use Kytario\Model\Entity\Enum\SystemRoleEnum;
use Kytario\Model\Repository\UserRepository;
use Kytario\Tests\Support\Fixture;
use Kytario\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AdminUserController::class)]
final class AdminUserControllerTest extends IntegrationTestCase
{
	public function testNonSysAdminCannotListUsers(): void
	{
		$user = Fixture::createUser();

		$response = $this->request('GET', '/api/admin/users', authenticatedAs: $user);
		self::assertSame(401, $response->getStatusCode());
	}

	public function testSysAdminCanListAndDeleteUsers(): void
	{
		$sysAdmin = Fixture::createUser(email: 'root@example.com', systemRole: SystemRoleEnum::SystemAdmin);
		$victim = Fixture::createUser(email: 'victim@example.com');

		$list = $this->request('GET', '/api/admin/users', authenticatedAs: $sysAdmin);
		self::assertSame(200, $list->getStatusCode());
		$emails = array_column($this->jsonList($list), 'email');
		self::assertContains('root@example.com', $emails);
		self::assertContains('victim@example.com', $emails);

		$delete = $this->request('DELETE', '/api/admin/users/' . $victim->id, authenticatedAs: $sysAdmin);
		self::assertSame(200, $delete->getStatusCode());

		$listAfter = $this->request('GET', '/api/admin/users', authenticatedAs: $sysAdmin);
		$emailsAfter = array_column($this->jsonList($listAfter), 'email');
		self::assertNotContains('victim@example.com', $emailsAfter);
	}

	public function testSysAdminCannotDeleteThemselves(): void
	{
		$sysAdmin = Fixture::createUser(systemRole: SystemRoleEnum::SystemAdmin);

		$response = $this->request('DELETE', '/api/admin/users/' . $sysAdmin->id, authenticatedAs: $sysAdmin);
		self::assertSame(409, $response->getStatusCode());
	}

	public function testAdminEmailChangeRejectsDuplicates(): void
	{
		$sysAdmin = Fixture::createUser(email: 'root@example.com', systemRole: SystemRoleEnum::SystemAdmin);
		Fixture::createUser(email: 'taken@example.com');
		$target = Fixture::createUser(email: 'target@example.com');

		$response = $this->request(
			'PATCH',
			'/api/admin/users/' . $target->id,
			body: ['email' => 'taken@example.com'],
			authenticatedAs: $sysAdmin,
		);

		self::assertSame(409, $response->getStatusCode());
	}

	public function testAdminEmailChangeResetsVerification(): void
	{
		$sysAdmin = Fixture::createUser(email: 'root@example.com', systemRole: SystemRoleEnum::SystemAdmin);
		$target = Fixture::createUser(email: 'old@example.com', emailVerified: true);

		$response = $this->request(
			'PATCH',
			'/api/admin/users/' . $target->id,
			body: ['email' => 'new@example.com'],
			authenticatedAs: $sysAdmin,
		);

		self::assertSame(200, $response->getStatusCode());

		$repo = $this->container->get(UserRepository::class);
		assert($repo instanceof UserRepository);
		$updated = $repo->findUserById($target->id);
		self::assertNotNull($updated);
		self::assertSame('new@example.com', $updated->email);
		self::assertFalse($updated->emailVerified);
	}

	public function testAdminEmailChangeRejectsMalformedAddress(): void
	{
		$sysAdmin = Fixture::createUser(email: 'root@example.com', systemRole: SystemRoleEnum::SystemAdmin);
		$target = Fixture::createUser(email: 'target@example.com');

		$response = $this->request(
			'PATCH',
			'/api/admin/users/' . $target->id,
			body: ['email' => 'not-an-email'],
			authenticatedAs: $sysAdmin,
		);

		self::assertSame(409, $response->getStatusCode());
	}
}
