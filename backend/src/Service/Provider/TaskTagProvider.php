<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use RuntimeException;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskTag;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\TagRepository;
use Ukolio\Model\Repository\TaskTagRepository;
use Ukolio\Service\Search\SearchIndexer;

final readonly class TaskTagProvider implements TaskTagProviderInterface
{
	public function __construct(
		private TaskTagRepository $taskTagRepository,
		private TagRepository $tagRepository,
		private SearchIndexer $searchIndexer,
	) {
	}

	/** @return list<int> */
	public function getTagIdsForTask(Task $task): array
	{
		$ids = [];
		foreach ($this->taskTagRepository->findByTask($task->id) as $taskTag) {
			$ids[] = $taskTag->tag->id;
		}
		sort($ids);
		return $ids;
	}

	/**
	 * @param list<int> $taskIds
	 * @return array<int, list<int>>
	 */
	public function getTagIdsByTaskIds(array $taskIds): array
	{
		$result = [];
		foreach ($taskIds as $taskId) {
			$result[$taskId] = [];
		}
		if ($taskIds === []) {
			return $result;
		}

		foreach ($taskIds as $taskId) {
			foreach ($this->taskTagRepository->findByTask($taskId) as $taskTag) {
				$result[$taskId][] = $taskTag->tag->id;
			}
			sort($result[$taskId]);
		}
		return $result;
	}

	/**
	 * @param list<int> $tagIds
	 * @return array{added: list<int>, removed: list<int>}
	 */
	public function setTagsForTask(Workspace $workspace, Task $task, array $tagIds): array
	{
		$desired = [];
		foreach ($tagIds as $tagId) {
			$tag = $this->tagRepository->findOneByWorkspaceAndId($workspace->id, $tagId);
			if ($tag === null) {
				throw new RuntimeException('Tag ' . $tagId . ' does not belong to this workspace.');
			}
			$desired[$tag->id] = $tag;
		}

		$existing = [];
		foreach ($this->taskTagRepository->findByTask($task->id) as $taskTag) {
			$existing[$taskTag->tag->id] = $taskTag;
		}

		$added = [];
		$removed = [];
		$now = new DateTimeImmutable();

		foreach ($desired as $tagId => $tag) {
			if (isset($existing[$tagId])) {
				continue;
			}
			$row = new TaskTag(task: $task, tag: $tag);
			$row->createdAt = $now;
			$row->updatedAt = $now;
			$this->taskTagRepository->persist($row);
			$added[] = $tagId;
		}

		foreach ($existing as $tagId => $row) {
			if (isset($desired[$tagId])) {
				continue;
			}
			$this->taskTagRepository->delete($row);
			$removed[] = $tagId;
		}

		if ($added !== [] || $removed !== []) {
			$this->searchIndexer->queueUpsert($task->id);
		}

		return ['added' => $added, 'removed' => $removed];
	}

	public function deleteAllForTask(Task $task): void
	{
		foreach ($this->taskTagRepository->findByTask($task->id) as $row) {
			$this->taskTagRepository->delete($row);
		}
	}
}
