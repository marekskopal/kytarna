<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use Iterator;
use RuntimeException;
use Ukolio\Model\Entity\Enum\EventTypeEnum;
use Ukolio\Model\Entity\Tag;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\TagRepository;
use Ukolio\Model\Repository\TaskTagRepository;
use Ukolio\Service\Search\SearchIndexer;

final readonly class TagProvider implements TagProviderInterface
{
	public function __construct(
		private TagRepository $tagRepository,
		private EventProviderInterface $eventProvider,
		private TaskTagRepository $taskTagRepository,
		private SearchIndexer $searchIndexer,
	) {
	}

	/** @return Iterator<Tag> */
	public function getTags(Workspace $workspace): Iterator
	{
		return $this->tagRepository->findByWorkspace($workspace->id);
	}

	public function getTag(Workspace $workspace, int $tagId): ?Tag
	{
		return $this->tagRepository->findOneByWorkspaceAndId($workspace->id, $tagId);
	}

	public function findTagByName(Workspace $workspace, string $name): ?Tag
	{
		return $this->tagRepository->findOneByWorkspaceAndName($workspace->id, trim($name));
	}

	public function createTag(User $author, Workspace $workspace, string $name, string $color): Tag
	{
		$name = $this->validateName($workspace->id, $name, null);
		$color = $this->validateColor($color);

		$now = new DateTimeImmutable();
		$tag = new Tag(workspace: $workspace, name: $name, color: $color);
		$tag->createdAt = $now;
		$tag->updatedAt = $now;
		$this->tagRepository->persist($tag);

		$this->eventProvider->recordWorkspaceEvent(
			$author,
			$workspace,
			EventTypeEnum::TagCreated,
			['tagId' => $tag->id, 'name' => $tag->name, 'color' => $tag->color],
		);

		return $tag;
	}

	public function updateTag(User $author, Tag $tag, string $name, string $color): Tag
	{
		$name = $this->validateName($tag->workspace->id, $name, $tag->id);
		$color = $this->validateColor($color);

		$nameChanged = $tag->name !== $name;

		$tag->name = $name;
		$tag->color = $color;
		$tag->updatedAt = new DateTimeImmutable();
		$this->tagRepository->persist($tag);

		$this->eventProvider->recordWorkspaceEvent(
			$author,
			$tag->workspace,
			EventTypeEnum::TagUpdated,
			['tagId' => $tag->id, 'name' => $tag->name, 'color' => $tag->color],
		);

		if ($nameChanged) {
			$this->searchIndexer->queueUpsertMany($this->findTaskIdsByTag($tag->id));
		}

		return $tag;
	}

	public function deleteTag(User $author, Tag $tag): void
	{
		$affectedTaskIds = $this->findTaskIdsByTag($tag->id);

		$this->eventProvider->recordWorkspaceEvent(
			$author,
			$tag->workspace,
			EventTypeEnum::TagDeleted,
			['tagId' => $tag->id, 'name' => $tag->name],
		);

		// task_tags rows cascade away via DB FK on delete.
		$this->tagRepository->delete($tag);

		$this->searchIndexer->queueUpsertMany($affectedTaskIds);
	}

	/** @return list<int> */
	private function findTaskIdsByTag(int $tagId): array
	{
		return $this->taskTagRepository->findTaskIdsByTagIds([$tagId]);
	}

	private function validateName(int $workspaceId, string $name, ?int $excludeTagId): string
	{
		$trimmed = trim($name);
		if ($trimmed === '') {
			throw new RuntimeException('Tag name cannot be empty.');
		}

		$existing = $this->tagRepository->findOneByWorkspaceAndName($workspaceId, $trimmed);
		if ($existing !== null && $existing->id !== $excludeTagId) {
			throw new RuntimeException('A tag with the name "' . $trimmed . '" already exists in this workspace.');
		}

		return $trimmed;
	}

	private function validateColor(string $color): string
	{
		$trimmed = trim($color);
		if (preg_match('/^#[0-9a-fA-F]{6}$/', $trimmed) !== 1) {
			throw new RuntimeException('Tag color must be a 7-character hex string, e.g. "#3b82f6".');
		}
		return strtolower($trimmed);
	}
}
