<?php

declare(strict_types=1);

namespace Kytario\Service\Auth;

use Kytario\Model\Entity\Enum\EventTypeEnum;
use Kytario\Model\Entity\Enum\SystemRoleEnum;
use Kytario\Model\Entity\User;
use Kytario\Model\Repository\UserRepository;
use Kytario\Model\Repository\WorkspaceUserRepository;
use Kytario\Service\Provider\EventProviderInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

final readonly class CurrentUserDeletionService implements CurrentUserDeletionServiceInterface
{
	public function __construct(
		private UserRepository $userRepository,
		private WorkspaceUserRepository $workspaceUserRepository,
		private AdminServiceInterface $adminService,
		private EventProviderInterface $eventProvider,
		private LoggerInterface $logger,
	) {
	}

	public function deleteSelf(User $user): void
	{
		$blocking = $this->adminService->findSoleOwnerWorkspaces($user);
		if ($blocking !== []) {
			throw new SoleOwnerException($blocking);
		}

		if (
			$user->systemRole === SystemRoleEnum::SystemAdmin
			&& $this->userRepository->countSystemAdmins() <= 1
		) {
			throw new RuntimeException('Cannot delete the last system administrator.');
		}

		foreach ($this->workspaceUserRepository->findByUser($user->id) as $membership) {
			$this->workspaceUserRepository->delete($membership);
		}

		$this->eventProvider->recordWorkspaceEvent(
			$user,
			null,
			EventTypeEnum::UserSelfDeleted,
			[
				'userId' => $user->id,
				'userEmail' => $user->email,
				'userName' => $user->name,
			],
		);

		$this->logger->info('user.self_deletion', [
			'userId' => $user->id,
			'userEmail' => $user->email,
		]);

		$this->userRepository->delete($user);
	}
}
