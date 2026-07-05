<?php

declare(strict_types=1);

namespace Kytario\Tests\Controller;

use Kytario\Controller\CurrentUserController;
use Kytario\Model\Entity\Enum\EventTypeEnum;
use Kytario\Model\Entity\Enum\SystemRoleEnum;
use Kytario\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytario\Model\Repository\EventRepository;
use Kytario\Model\Repository\UserRepository;
use Kytario\Tests\Support\AppHarness;
use Kytario\Tests\Support\Fixture;
use Kytario\Tests\Support\IntegrationTestCase;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CurrentUserController::class)]
final class CurrentUserControllerTest extends IntegrationTestCase
{
	public function testGetCurrentUserReturnsAuthenticatedProfile(): void
	{
		$user = Fixture::createUser(email: 'me@example.com', name: 'Me');

		$response = $this->request('GET', '/api/current-user', authenticatedAs: $user);

		self::assertSame(200, $response->getStatusCode());
		$body = $this->jsonBody($response);
		self::assertSame('me@example.com', $body['email']);
		self::assertSame('Me', $body['name']);
		self::assertArrayHasKey('onboardingCompletedAt', $body);
		self::assertNull($body['onboardingCompletedAt']);
	}

	public function testPostOnboardingCompleteSetsTimestamp(): void
	{
		$user = Fixture::createUser();

		$response = $this->request('POST', '/api/current-user/onboarding-complete', authenticatedAs: $user);

		self::assertSame(200, $response->getStatusCode());
		$body = $this->jsonBody($response);
		self::assertIsString($body['onboardingCompletedAt']);

		$userRepository = AppHarness::container()->get(UserRepository::class);
		assert($userRepository instanceof UserRepository);
		$persisted = $userRepository->findUserById($user->id);
		self::assertNotNull($persisted);
		self::assertNotNull($persisted->onboardingCompletedAt);
	}

	public function testPostOnboardingCompleteIsIdempotent(): void
	{
		$user = Fixture::createUser();

		$first = $this->request('POST', '/api/current-user/onboarding-complete', authenticatedAs: $user);
		self::assertSame(200, $first->getStatusCode());
		$initial = $this->jsonBody($first)['onboardingCompletedAt'];
		self::assertIsString($initial);

		$second = $this->request('POST', '/api/current-user/onboarding-complete', authenticatedAs: $user);
		self::assertSame(200, $second->getStatusCode());
		self::assertSame($initial, $this->jsonBody($second)['onboardingCompletedAt']);
	}

	public function testPostOnboardingCompleteRequiresAuth(): void
	{
		$response = $this->request('POST', '/api/current-user/onboarding-complete');
		self::assertSame(401, $response->getStatusCode());
	}

	public function testPatchUpdatesNameAndLocale(): void
	{
		$user = Fixture::createUser();

		$response = $this->request(
			'PATCH',
			'/api/current-user',
			body: ['name' => 'Renamed', 'locale' => 'cs'],
			authenticatedAs: $user,
		);

		self::assertSame(200, $response->getStatusCode());
		$body = $this->jsonBody($response);
		self::assertSame('Renamed', $body['name']);
		self::assertSame('cs', $body['locale']);
	}

	public function testPatchRejectsUnknownLocale(): void
	{
		$user = Fixture::createUser();

		$response = $this->request(
			'PATCH',
			'/api/current-user',
			body: ['locale' => 'klingon'],
			authenticatedAs: $user,
		);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testPatchUpdatesTheme(): void
	{
		$user = Fixture::createUser();

		$response = $this->request(
			'PATCH',
			'/api/current-user',
			body: ['theme' => 'dark'],
			authenticatedAs: $user,
		);

		self::assertSame(200, $response->getStatusCode());
		self::assertSame('dark', $this->jsonBody($response)['theme']);
	}

	public function testPatchRejectsUnknownTheme(): void
	{
		$user = Fixture::createUser();

		$response = $this->request(
			'PATCH',
			'/api/current-user',
			body: ['theme' => 'mauve'],
			authenticatedAs: $user,
		);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testChangePasswordRequiresCorrectCurrentPassword(): void
	{
		$user = Fixture::createUser(password: 'OldPass11');

		$bad = $this->request(
			'POST',
			'/api/current-user/password',
			body: ['currentPassword' => 'WrongPass1', 'newPassword' => 'NewPass11'],
			authenticatedAs: $user,
		);
		self::assertSame(401, $bad->getStatusCode());

		$ok = $this->request(
			'POST',
			'/api/current-user/password',
			body: ['currentPassword' => 'OldPass11', 'newPassword' => 'NewPass22'],
			authenticatedAs: $user,
		);
		self::assertSame(200, $ok->getStatusCode());

		// Confirm new password authenticates
		$login = $this->request('POST', '/api/authentication/login', [
			'email' => $user->email,
			'password' => 'NewPass22',
		]);
		self::assertSame(200, $login->getStatusCode());
	}

	public function testChangePasswordRejectsWeakPassword(): void
	{
		$user = Fixture::createUser(password: 'OldPass11');

		$response = $this->request(
			'POST',
			'/api/current-user/password',
			body: ['currentPassword' => 'OldPass11', 'newPassword' => 'weak'],
			authenticatedAs: $user,
		);
		self::assertSame(422, $response->getStatusCode());
	}

	public function testDeleteCurrentUserRemovesAccountAndAuditEventOutlivesUser(): void
	{
		$owner = Fixture::createUser(email: 'owner@example.com');
		$workspace = Fixture::createWorkspace($owner, 'Shared');
		$member = Fixture::createUser(email: 'member@example.com', password: 'MemberPass1');
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);

		$response = $this->request('DELETE', '/api/current-user', authenticatedAs: $member);
		self::assertSame(200, $response->getStatusCode());

		$userRepository = AppHarness::container()->get(UserRepository::class);
		assert($userRepository instanceof UserRepository);
		self::assertNull($userRepository->findUserByEmail('member@example.com'));

		$eventRepository = AppHarness::container()->get(EventRepository::class);
		assert($eventRepository instanceof EventRepository);
		$auditEvents = [];
		foreach ($eventRepository->findByAuthor($member->id) as $event) {
			$auditEvents[] = $event;
		}
		self::assertSame([], $auditEvents, 'cascade should null out author_id, removing the event from findByAuthor');

		$pdo = AppHarness::pdo();
		$stmt = $pdo->prepare('SELECT author_id, metadata FROM events WHERE type = :type ORDER BY id DESC LIMIT 1');
		assert($stmt !== false);
		$stmt->execute(['type' => EventTypeEnum::UserSelfDeleted->value]);
		/** @var array{author_id: int|null, metadata: string}|false $row */
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		self::assertNotFalse($row, 'UserSelfDeleted event should survive the deletion');
		self::assertNull($row['author_id'], 'author FK should be SET NULL after the user is deleted');
		self::assertStringContainsString('member@example.com', $row['metadata']);
	}

	public function testDeleteCurrentUserBlockedForWorkspaceOwner(): void
	{
		$owner = Fixture::createUser(email: 'soleowner@example.com');
		Fixture::createWorkspace($owner, 'Solo Workspace');

		$response = $this->request('DELETE', '/api/current-user', authenticatedAs: $owner);
		self::assertSame(409, $response->getStatusCode());

		$body = $this->jsonBody($response);
		self::assertSame(409, $body['code']);
		$message = $body['message'];
		self::assertIsString($message);
		self::assertStringContainsString('Solo Workspace', $message);
		self::assertIsArray($body['workspaces']);
		self::assertCount(1, $body['workspaces']);
		$firstWorkspace = $body['workspaces'][0];
		self::assertIsArray($firstWorkspace);
		self::assertSame('Solo Workspace', $firstWorkspace['name']);

		$userRepository = AppHarness::container()->get(UserRepository::class);
		assert($userRepository instanceof UserRepository);
		self::assertNotNull($userRepository->findUserByEmail('soleowner@example.com'));
	}

	public function testDeleteCurrentUserBlocksLastSystemAdmin(): void
	{
		$sysAdmin = Fixture::createUser(systemRole: SystemRoleEnum::SystemAdmin);

		$response = $this->request('DELETE', '/api/current-user', authenticatedAs: $sysAdmin);
		self::assertSame(409, $response->getStatusCode());

		$userRepository = AppHarness::container()->get(UserRepository::class);
		assert($userRepository instanceof UserRepository);
		self::assertNotNull($userRepository->findUserById($sysAdmin->id));
	}

	public function testDeleteCurrentUserRequiresAuth(): void
	{
		$response = $this->request('DELETE', '/api/current-user');
		self::assertSame(401, $response->getStatusCode());
	}

	public function testExportReturnsAttachmentWithUserAndMemberships(): void
	{
		$owner = Fixture::createUser(email: 'owner@example.com');
		$workspace = Fixture::createWorkspace($owner, 'Acme');
		$me = Fixture::createUser(email: 'me@example.com', name: 'Me');
		Fixture::addMember($workspace, $me, WorkspaceRoleEnum::Member);

		$response = $this->request('GET', '/api/current-user/export', authenticatedAs: $me);
		self::assertSame(200, $response->getStatusCode());

		$disposition = $response->getHeaderLine('Content-Disposition');
		self::assertStringContainsString('attachment', $disposition);
		self::assertStringContainsString(sprintf('kytario-export-%d.json', $me->id), $disposition);

		$body = $this->jsonBody($response);
		$userBlock = $body['user'];
		self::assertIsArray($userBlock);
		self::assertSame('me@example.com', $userBlock['email']);
		self::assertSame('Me', $userBlock['name']);
		self::assertArrayHasKey('exportedAt', $body);
		$memberships = $body['workspaceMemberships'];
		self::assertIsArray($memberships);
		$workspaceNames = array_column($memberships, 'workspaceName');
		self::assertContains('Acme', $workspaceNames);
		self::assertArrayHasKey('events', $body);
		self::assertArrayHasKey('lectureComments', $body);
		self::assertArrayHasKey('lectureFiles', $body);
		self::assertArrayHasKey('lectureRelationsCreated', $body);
		self::assertArrayHasKey('oauthClients', $body);
		self::assertArrayHasKey('invitationsSent', $body);
	}

	public function testExportRequiresAuth(): void
	{
		$response = $this->request('GET', '/api/current-user/export');
		self::assertSame(401, $response->getStatusCode());
	}
}
