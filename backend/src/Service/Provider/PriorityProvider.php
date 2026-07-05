<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use Iterator;
use RuntimeException;
use Ukolio\Model\Entity\Priority;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\PriorityRepository;
use Ukolio\Model\Repository\TaskRepository;

final readonly class PriorityProvider implements PriorityProviderInterface
{
	public function __construct(private PriorityRepository $priorityRepository, private TaskRepository $taskRepository,)
	{
	}

	/** @return Iterator<Priority> */
	public function getPriorities(Workspace $workspace): Iterator
	{
		return $this->priorityRepository->findByWorkspace($workspace->id);
	}

	public function getPriority(Workspace $workspace, int $priorityId): ?Priority
	{
		return $this->priorityRepository->findOneByWorkspaceAndId($workspace->id, $priorityId);
	}

	public function findPriorityByName(Workspace $workspace, string $name): ?Priority
	{
		return $this->priorityRepository->findOneByWorkspaceAndName($workspace->id, $name);
	}

	public function getDefaultForWorkspace(Workspace $workspace): ?Priority
	{
		$default = $this->priorityRepository->findDefaultForWorkspace($workspace->id);
		if ($default !== null) {
			return $default;
		}
		// Fallback: first priority by position when nothing is flagged default.
		foreach ($this->priorityRepository->findByWorkspace($workspace->id) as $priority) {
			return $priority;
		}
		return null;
	}

	public function createPriority(Workspace $workspace, string $name, string $color, bool $isDefault): Priority
	{
		$name = $this->validateName($workspace->id, $name, null);
		$color = $this->validateColor($color);

		if ($isDefault) {
			$this->clearDefaultFlag($workspace->id, null);
		}

		$now = new DateTimeImmutable();
		$priority = new Priority(
			workspace: $workspace,
			name: $name,
			color: $color,
			position: $this->nextPosition($workspace),
			isDefault: $isDefault,
		);
		$priority->createdAt = $now;
		$priority->updatedAt = $now;
		$this->priorityRepository->persist($priority);

		return $priority;
	}

	public function updatePriority(Priority $priority, string $name, string $color, bool $isDefault): Priority
	{
		$name = $this->validateName($priority->workspace->id, $name, $priority->id);
		$color = $this->validateColor($color);

		if ($isDefault && !$priority->isDefault) {
			$this->clearDefaultFlag($priority->workspace->id, $priority->id);
		}

		$priority->name = $name;
		$priority->color = $color;
		$priority->isDefault = $isDefault;
		$priority->updatedAt = new DateTimeImmutable();
		$this->priorityRepository->persist($priority);

		return $priority;
	}

	public function movePriority(Priority $priority, int $newPosition): Priority
	{
		$oldPosition = $priority->position;
		if ($oldPosition === $newPosition) {
			return $priority;
		}

		$siblings = iterator_to_array($this->priorityRepository->findByWorkspace($priority->workspace->id), false);

		foreach ($siblings as $sibling) {
			if ($sibling->id === $priority->id) {
				continue;
			}

			if ($oldPosition < $newPosition) {
				if ($sibling->position > $oldPosition && $sibling->position <= $newPosition) {
					$sibling->position--;
					$sibling->updatedAt = new DateTimeImmutable();
					$this->priorityRepository->persist($sibling);
				}
			} else {
				if ($sibling->position >= $newPosition && $sibling->position < $oldPosition) {
					$sibling->position++;
					$sibling->updatedAt = new DateTimeImmutable();
					$this->priorityRepository->persist($sibling);
				}
			}
		}

		$priority->position = $newPosition;
		$priority->updatedAt = new DateTimeImmutable();
		$this->priorityRepository->persist($priority);

		return $priority;
	}

	public function deletePriority(Priority $priority): void
	{
		$dependentTasks = $this->taskRepository->countByPriority($priority->id);
		if ($dependentTasks > 0) {
			throw new PriorityInUseException($dependentTasks, $priority->name);
		}

		$this->priorityRepository->delete($priority);
	}

	private function validateName(int $workspaceId, string $name, ?int $excludePriorityId): string
	{
		$trimmed = trim($name);
		if ($trimmed === '') {
			throw new RuntimeException('Priority name cannot be empty.');
		}

		$existing = $this->priorityRepository->findOneByWorkspaceAndName($workspaceId, $trimmed);
		if ($existing !== null && $existing->id !== $excludePriorityId) {
			throw new RuntimeException('A priority with the name "' . $trimmed . '" already exists in this workspace.');
		}

		return $trimmed;
	}

	private function validateColor(string $color): string
	{
		$trimmed = trim($color);
		if (preg_match('/^#[0-9a-fA-F]{6}$/', $trimmed) !== 1) {
			throw new RuntimeException('Priority color must be a 7-character hex string, e.g. "#3b82f6".');
		}
		return strtolower($trimmed);
	}

	private function clearDefaultFlag(int $workspaceId, ?int $exceptPriorityId): void
	{
		foreach ($this->priorityRepository->findByWorkspace($workspaceId) as $sibling) {
			if ($sibling->isDefault && $sibling->id !== $exceptPriorityId) {
				$sibling->isDefault = false;
				$sibling->updatedAt = new DateTimeImmutable();
				$this->priorityRepository->persist($sibling);
			}
		}
	}

	private function nextPosition(Workspace $workspace): int
	{
		$max = -1;
		foreach ($this->priorityRepository->findByWorkspace($workspace->id) as $priority) {
			if ($priority->position > $max) {
				$max = $priority->position;
			}
		}
		return $max + 1;
	}
}
