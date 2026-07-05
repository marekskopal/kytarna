<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\ProjectRepository;
use Ukolio\Model\Repository\TaskRepository;

final readonly class TaskCodeResolver implements TaskCodeResolverInterface
{
	public function __construct(
		private ProjectRepository $projectRepository,
		private TaskRepository $taskRepository,
		private WorkspaceProviderInterface $workspaceProvider,
	) {
	}

	public function resolveForUser(User $user, string $idOrCode): ?Task
	{
		// The security boundary here is workspace membership, not the active workspace:
		// a user legitimately belongs to several workspaces at once, so a task is reachable
		// when the user is a member of the task's own workspace. Numeric IDs resolve across
		// all of the user's memberships; project codes (PREFIX-N) are looked up in the
		// active workspace to avoid ambiguity between workspaces that share a prefix.
		$task = ctype_digit($idOrCode)
			? $this->taskRepository->findById((int) $idOrCode)
			: $this->resolveCodeInCurrentWorkspace($user, $idOrCode);

		if ($task === null || !$this->workspaceProvider->isMember($user, $task->project->workspace)) {
			return null;
		}

		return $task;
	}

	private function resolveCodeInCurrentWorkspace(User $user, string $code): ?Task
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);

		return $workspace === null ? null : $this->findByCode($workspace, $code);
	}

	public function findByCode(Workspace $workspace, string $code): ?Task
	{
		if (preg_match('/^([A-Z0-9]+)-(\d+)$/', strtoupper(trim($code)), $matches) !== 1) {
			return null;
		}
		$project = $this->projectRepository->findByWorkspaceAndPrefix($workspace->id, $matches[1]);
		return $project === null ? null : $this->taskRepository->findByProjectAndSequence($project->id, (int) $matches[2]);
	}

	public function resolve(Workspace $workspace, string $idOrCode): ?Task
	{
		// Strictly scoped to the given workspace for BOTH numeric IDs and project codes,
		// so the workspace parameter is an enforced boundary rather than a hint. Callers
		// that need cross-workspace, membership-based resolution must use resolveForUser().
		if (ctype_digit($idOrCode)) {
			$task = $this->taskRepository->findById((int) $idOrCode);

			return $task !== null && $task->project->workspace->id === $workspace->id ? $task : null;
		}

		return $this->findByCode($workspace, $idOrCode);
	}
}
