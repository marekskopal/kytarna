<?php

declare(strict_types=1);

namespace Ukolio\Controller;

use DateTimeImmutable;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Ukolio\Dto\InvitationCreateDto;
use Ukolio\Dto\InvitationDto;
use Ukolio\Dto\InvitationTokenDto;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Model\Repository\InvitationRepository;
use Ukolio\Response\ErrorResponse;
use Ukolio\Response\NotAuthorizedResponse;
use Ukolio\Response\NotFoundResponse;
use Ukolio\Response\OkResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Auth\PermissionCheckerInterface;
use Ukolio\Service\Authentication\RateLimitConfig;
use Ukolio\Service\Provider\InvitationProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Request\RequestServiceInterface;

final readonly class InvitationController
{
	public function __construct(
		private InvitationProviderInterface $invitationProvider,
		private InvitationRepository $invitationRepository,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
		private RequestServiceInterface $requestService,
		private RateLimitConfig $rateLimitConfig,
	) {
	}

	#[RouteGet(Routes::WorkspaceInvitations->value)]
	public function actionGetInvitations(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		if (!$this->permissionChecker->canManageMembers($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have permission to view invitations.');
		}

		$invitations = [];
		foreach ($this->invitationProvider->getInvitations($workspace) as $invitation) {
			$invitations[] = InvitationDto::fromEntity($invitation);
		}

		return new JsonResponse($invitations);
	}

	#[RoutePost(Routes::WorkspaceInvitations->value)]
	public function actionPostInvitation(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		if (!$this->permissionChecker->canManageMembers($user, $workspace)) {
			return new NotAuthorizedResponse('You do not have permission to invite members.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, InvitationCreateDto::class);
		$role = WorkspaceRoleEnum::tryFrom($dto->role) ?? WorkspaceRoleEnum::Member;

		if (!$this->permissionChecker->canInviteAs($user, $workspace, $role)) {
			return new NotAuthorizedResponse('You cannot invite a member with this role.');
		}

		$recentCount = $this->invitationRepository->countByWorkspaceSince(
			$workspace->id,
			(new DateTimeImmutable())->modify('-1 hour'),
		);
		if ($recentCount >= $this->rateLimitConfig->invitationsPerHour) {
			return new ErrorResponse(
				'This workspace has reached its hourly invitation limit. Please try again later.',
				429,
				['Retry-After' => '3600'],
			);
		}

		try {
			$invitation = $this->invitationProvider->createInvitation($user, $workspace, $dto->email, $role);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(InvitationDto::fromEntity($invitation));
	}

	#[RouteDelete(Routes::Invitation->value)]
	public function actionDeleteInvitation(ServerRequestInterface $request, int $invitationId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);

		foreach ($this->workspaceProvider->getMemberships($user) as $membership) {
			if (!$this->permissionChecker->canManageMembers($user, $membership->workspace)) {
				continue;
			}
			foreach ($this->invitationProvider->getInvitations($membership->workspace) as $invitation) {
				if ($invitation->id === $invitationId) {
					$this->invitationProvider->deleteInvitation($invitation);
					return new OkResponse();
				}
			}
		}

		return new NotFoundResponse('Invitation not found.');
	}

	#[RoutePost(Routes::InvitationLookup->value)]
	public function actionPostLookup(ServerRequestInterface $request): ResponseInterface
	{
		$dto = $this->requestService->getRequestBodyDto($request, InvitationTokenDto::class);

		$invitation = $this->invitationProvider->findByToken($dto->token);
		if ($invitation === null) {
			return new NotFoundResponse('Invitation not found.');
		}

		return new JsonResponse(InvitationDto::fromEntity($invitation));
	}

	#[RoutePost(Routes::InvitationAccept->value)]
	public function actionPostAccept(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$dto = $this->requestService->getRequestBodyDto($request, InvitationTokenDto::class);

		$invitation = $this->invitationProvider->findByToken($dto->token);
		if ($invitation === null) {
			return new NotFoundResponse('Invitation not found.');
		}

		try {
			$this->invitationProvider->acceptInvitation($user, $invitation);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(InvitationDto::fromEntity($invitation));
	}
}
