<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use Iterator;
use RuntimeException;
use Ukolio\Model\Entity\SavedView;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\SavedViewRepository;
use Ukolio\Model\Repository\UserRepository;
use const JSON_THROW_ON_ERROR;

final readonly class SavedViewProvider implements SavedViewProviderInterface
{
	private const int MaxNameLength = 80;
	private const int MaxFilterConfigLength = 4096;

	public function __construct(private SavedViewRepository $savedViewRepository, private UserRepository $userRepository,)
	{
	}

	/** @return Iterator<SavedView> */
	public function getViews(Workspace $workspace, User $user): Iterator
	{
		return $this->savedViewRepository->findByWorkspaceAndUser($workspace->id, $user->id);
	}

	public function getViewForUser(int $viewId, User $user): ?SavedView
	{
		return $this->savedViewRepository->findOneByIdForUser($viewId, $user->id);
	}

	public function createView(User $user, Workspace $workspace, string $name, string $filterConfig): SavedView
	{
		$name = $this->validateName($workspace->id, $user->id, $name, null);
		$filterConfig = $this->validateFilterConfig($filterConfig);

		$now = new DateTimeImmutable();
		$view = new SavedView(workspace: $workspace, user: $user, name: $name, filterConfig: $filterConfig);
		$view->createdAt = $now;
		$view->updatedAt = $now;
		$this->savedViewRepository->persist($view);

		return $view;
	}

	public function updateView(SavedView $view, string $name, string $filterConfig): SavedView
	{
		$name = $this->validateName($view->workspace->id, $view->user->id, $name, $view->id);
		$filterConfig = $this->validateFilterConfig($filterConfig);

		$view->name = $name;
		$view->filterConfig = $filterConfig;
		$view->updatedAt = new DateTimeImmutable();
		$this->savedViewRepository->persist($view);

		return $view;
	}

	public function deleteView(SavedView $view): void
	{
		$owner = $view->user;
		if ($owner->defaultSavedViewId === $view->id) {
			$owner->defaultSavedViewId = null;
			$owner->updatedAt = new DateTimeImmutable();
			$this->userRepository->persist($owner);
		}

		$this->savedViewRepository->delete($view);
	}

	private function validateName(int $workspaceId, int $userId, string $name, ?int $excludeViewId): string
	{
		$trimmed = trim($name);
		if ($trimmed === '') {
			throw new RuntimeException('View name cannot be empty.');
		}
		if (mb_strlen($trimmed) > self::MaxNameLength) {
			throw new RuntimeException(sprintf('View name is too long (max %d characters).', self::MaxNameLength));
		}

		$existing = $this->savedViewRepository->findOneByWorkspaceUserName($workspaceId, $userId, $trimmed);
		if ($existing !== null && $existing->id !== $excludeViewId) {
			throw new RuntimeException('A view with the name "' . $trimmed . '" already exists.');
		}

		return $trimmed;
	}

	private function validateFilterConfig(string $filterConfig): string
	{
		if (mb_strlen($filterConfig) > self::MaxFilterConfigLength) {
			throw new RuntimeException(sprintf('Filter config is too large (max %d characters).', self::MaxFilterConfigLength));
		}

		// Validate that it parses as JSON so we don't store garbage. Backend treats the parsed value opaquely.
		try {
			json_decode($filterConfig, false, 4, JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			throw new RuntimeException('Filter config must be valid JSON.', 0, $e);
		}

		return $filterConfig;
	}
}
