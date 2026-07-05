<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Tool;

use DateTimeImmutable;
use Mcp\Capability\Attribute\McpTool;
use RuntimeException;
use Ukolio\Mcp\Dto\McpInvitationDto;
use Ukolio\Mcp\Dto\McpMemberDto;
use Ukolio\Mcp\Dto\McpMemberListDto;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\InvitationRepository;
use Ukolio\Service\Auth\PermissionCheckerInterface;
use Ukolio\Service\Authentication\RateLimitConfig;
use Ukolio\Service\Provider\InvitationProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;

final readonly class MemberTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
		private InvitationProviderInterface $invitationProvider,
		private InvitationRepository $invitationRepository,
		private RateLimitConfig $rateLimitConfig,
	) {
	}

	/** List all members of the user's current workspace. */
	#[McpTool(name: 'list_workspace_members', description: 'List members of the current workspace with userId, name, email, and role.')]
	public function listWorkspaceMembers(): McpMemberListDto
	{
		$user = $this->userContext->getUser();
		$workspace = $this->requireWorkspace();

		if (!$this->permissionChecker->canViewWorkspace($user, $workspace)) {
			throw new RuntimeException('You do not have access to this workspace.');
		}

		$members = [];
		foreach ($this->workspaceProvider->getMembers($workspace) as $membership) {
			$members[] = McpMemberDto::fromEntity($membership);
		}

		return new McpMemberListDto($members);
	}

	/**
	 * Find a workspace member by email (case-insensitive exact match). Returns null if not found.
	 * Use this to resolve a human-readable email into a userId before calling update_task or create_task.
	 *
	 * @param string $email Email to look up
	 */
	#[McpTool(
		name: 'find_member_by_email',
		description: 'Find a member of the current workspace by email (case-insensitive). Returns null if not found.',
	)]
	public function findMemberByEmail(string $email): ?McpMemberDto
	{
		$user = $this->userContext->getUser();
		$workspace = $this->requireWorkspace();

		if (!$this->permissionChecker->canViewWorkspace($user, $workspace)) {
			throw new RuntimeException('You do not have access to this workspace.');
		}

		$needle = mb_strtolower(trim($email));
		if ($needle === '') {
			return null;
		}

		foreach ($this->workspaceProvider->getMembers($workspace) as $membership) {
			if (mb_strtolower($membership->user->email) === $needle) {
				return McpMemberDto::fromEntity($membership);
			}
		}

		return null;
	}

	/**
	 * Invite a new member to the current workspace by email. Sends an invitation email and creates
	 * a pending invitation row. Requires Owner or Admin in the workspace; Admins can only invite Members.
	 *
	 * @param string $email Invitee email address
	 * @param string|null $role Role to invite as: "Member" (default) or "Admin". "Owner" is not allowed.
	 */
	#[McpTool(
		name: 'invite_member',
		description: 'Invite a user by email to the current workspace. Owner/Admin only; Admins can only invite Members.',
	)]
	public function inviteMember(string $email, ?string $role = null): McpInvitationDto
	{
		$user = $this->userContext->getUser();
		$workspace = $this->requireWorkspace();

		if (!$this->permissionChecker->canManageMembers($user, $workspace)) {
			throw new RuntimeException('You do not have permission to invite members.');
		}

		$resolvedRole = $role !== null
			? (WorkspaceRoleEnum::tryFrom($role) ?? throw new RuntimeException(sprintf('Unknown role "%s".', $role)))
			: WorkspaceRoleEnum::Member;

		if (!$this->permissionChecker->canInviteAs($user, $workspace, $resolvedRole)) {
			throw new RuntimeException('You cannot invite a member with this role.');
		}

		$recentCount = $this->invitationRepository->countByWorkspaceSince(
			$workspace->id,
			(new DateTimeImmutable())->modify('-1 hour'),
		);
		if ($recentCount >= $this->rateLimitConfig->invitationsPerHour) {
			throw new RuntimeException('This workspace has reached its hourly invitation limit. Please try again later.');
		}

		$invitation = $this->invitationProvider->createInvitation($user, $workspace, $email, $resolvedRole);

		return McpInvitationDto::fromEntity($invitation);
	}

	private function requireWorkspace(): Workspace
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->userContext->getUser());
		if ($workspace === null) {
			throw new RuntimeException('No active workspace.');
		}

		return $workspace;
	}
}
