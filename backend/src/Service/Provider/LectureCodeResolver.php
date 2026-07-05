<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use Kytario\Model\Entity\Lecture;
use Kytario\Model\Entity\User;
use Kytario\Model\Entity\Workspace;
use Kytario\Model\Repository\CourseRepository;
use Kytario\Model\Repository\LectureRepository;

final readonly class LectureCodeResolver implements LectureCodeResolverInterface
{
	public function __construct(
		private CourseRepository $courseRepository,
		private LectureRepository $lectureRepository,
		private WorkspaceProviderInterface $workspaceProvider,
	) {
	}

	public function resolveForUser(User $user, string $idOrCode): ?Lecture
	{
		// The security boundary here is workspace membership, not the active workspace:
		// a user legitimately belongs to several workspaces at once, so a lecture is reachable
		// when the user is a member of the lecture's own workspace. Numeric IDs resolve across
		// all of the user's memberships; course codes (PREFIX-N) are looked up in the
		// active workspace to avoid ambiguity between workspaces that share a prefix.
		$lecture = ctype_digit($idOrCode)
			? $this->lectureRepository->findById((int) $idOrCode)
			: $this->resolveCodeInCurrentWorkspace($user, $idOrCode);

		if ($lecture === null || !$this->workspaceProvider->isMember($user, $lecture->course->workspace)) {
			return null;
		}

		return $lecture;
	}

	private function resolveCodeInCurrentWorkspace(User $user, string $code): ?Lecture
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);

		return $workspace === null ? null : $this->findByCode($workspace, $code);
	}

	public function findByCode(Workspace $workspace, string $code): ?Lecture
	{
		if (preg_match('/^([A-Z0-9]+)-(\d+)$/', strtoupper(trim($code)), $matches) !== 1) {
			return null;
		}
		$course = $this->courseRepository->findByWorkspaceAndPrefix($workspace->id, $matches[1]);
		return $course === null ? null : $this->lectureRepository->findByCourseAndSequence($course->id, (int) $matches[2]);
	}

	public function resolve(Workspace $workspace, string $idOrCode): ?Lecture
	{
		// Strictly scoped to the given workspace for BOTH numeric IDs and course codes,
		// so the workspace parameter is an enforced boundary rather than a hint. Callers
		// that need cross-workspace, membership-based resolution must use resolveForUser().
		if (ctype_digit($idOrCode)) {
			$lecture = $this->lectureRepository->findById((int) $idOrCode);

			return $lecture !== null && $lecture->course->workspace->id === $workspace->id ? $lecture : null;
		}

		return $this->findByCode($workspace, $idOrCode);
	}
}
