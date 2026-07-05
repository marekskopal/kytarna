<?php

declare(strict_types=1);

namespace Ukolio\Tests\Controller;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use Ukolio\Controller\InvitationController;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Model\Entity\Invitation;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\InvitationRepository;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(InvitationController::class)]
final class InvitationControllerTest extends IntegrationTestCase
{
	public function testOwnerCanCreateInvitationAndMemberCannot(): void
	{
		$owner = Fixture::createUser(email: 'owner@example.com');
		$member = Fixture::createUser(email: 'member@example.com');
		$workspace = Fixture::createWorkspace($owner);
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);

		$denied = $this->request(
			'POST',
			'/api/workspaces/' . $workspace->id . '/invitations',
			body: ['email' => 'invitee@example.com', 'role' => 'Member'],
			authenticatedAs: $member,
		);
		self::assertSame(401, $denied->getStatusCode());

		$ok = $this->request(
			'POST',
			'/api/workspaces/' . $workspace->id . '/invitations',
			body: ['email' => 'invitee@example.com', 'role' => 'Member'],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $ok->getStatusCode());
	}

	public function testInvitationLookupAndAcceptanceFlow(): void
	{
		$owner = Fixture::createUser(email: 'owner@example.com');
		$workspace = Fixture::createWorkspace($owner);

		// Seed an invitation directly with a known token (the provider only stores the hash).
		[$rawToken] = $this->seedInvitation($workspace, $owner, 'invitee@example.com');

		$invitee = Fixture::createUser(email: 'invitee@example.com');

		$lookup = $this->request('POST', '/api/invitations/lookup', body: ['token' => $rawToken]);
		self::assertSame(200, $lookup->getStatusCode());
		self::assertSame('invitee@example.com', $this->jsonBody($lookup)['email']);

		$accept = $this->request(
			'POST',
			'/api/invitations/accept',
			body: ['token' => $rawToken],
			authenticatedAs: $invitee,
		);
		self::assertSame(200, $accept->getStatusCode());

		// Invitee is now a member of the workspace.
		$workspaces = $this->request('GET', '/api/workspaces', authenticatedAs: $invitee);
		$list = $this->jsonList($workspaces);
		self::assertCount(1, $list);
		self::assertSame($workspace->id, $list[0]['id']);
	}

	public function testInvitationAcceptRequiresMatchingEmail(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		[$rawToken] = $this->seedInvitation($workspace, $owner, 'intended@example.com');

		$wrongUser = Fixture::createUser(email: 'someoneelse@example.com');

		$response = $this->request(
			'POST',
			'/api/invitations/accept',
			body: ['token' => $rawToken],
			authenticatedAs: $wrongUser,
		);
		self::assertSame(422, $response->getStatusCode());
	}

	public function testWorkspaceInvitationCapReturns429(): void
	{
		$owner = Fixture::createUser(email: 'capowner@example.com');
		$workspace = Fixture::createWorkspace($owner);

		// Seed 50 fresh invitations (the configured per-hour cap default).
		for ($i = 0; $i < 50; $i++) {
			$this->seedInvitation($workspace, $owner, 'seed' . $i . '@example.com');
		}

		$response = $this->request(
			'POST',
			'/api/workspaces/' . $workspace->id . '/invitations',
			body: ['email' => 'over-cap@example.com', 'role' => 'Member'],
			authenticatedAs: $owner,
		);

		self::assertSame(429, $response->getStatusCode());
		self::assertSame('3600', $response->getHeaderLine('Retry-After'));
	}

	/** @return array{0:string,1:Invitation} */
	private function seedInvitation(Workspace $workspace, User $inviter, string $email): array
	{
		$repo = $this->container->get(InvitationRepository::class);
		assert($repo instanceof InvitationRepository);

		$rawToken = bin2hex(random_bytes(16));
		$now = new DateTimeImmutable();
		$invitation = new Invitation(
			workspace: $workspace,
			inviter: $inviter,
			email: $email,
			tokenHash: hash('sha256', $rawToken),
			role: WorkspaceRoleEnum::Member,
			expiresAt: $now->modify('+7 days'),
		);
		$invitation->createdAt = $now;
		$invitation->updatedAt = $now;
		$invitation->acceptedAt = null;
		$repo->persist($invitation);

		return [$rawToken, $invitation];
	}
}
