<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use DateTimeImmutable;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\SongTag;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Model\Repository\SongTagRepository;
use Kytarna\Model\Repository\TagRepository;
use RuntimeException;

final readonly class SongTagProvider implements SongTagProviderInterface
{
	public function __construct(private SongTagRepository $songTagRepository, private TagRepository $tagRepository,)
	{
	}

	/** @return list<int> */
	public function getTagIdsForSong(Song $song): array
	{
		$ids = [];
		foreach ($this->songTagRepository->findBySong($song->id) as $songTag) {
			$ids[] = $songTag->tag->id;
		}
		sort($ids);
		return $ids;
	}

	/**
	 * @param list<int> $songIds
	 * @return array<int, list<int>>
	 */
	public function getTagIdsBySongIds(array $songIds): array
	{
		$result = [];
		foreach ($songIds as $songId) {
			$result[$songId] = [];
		}
		if ($songIds === []) {
			return $result;
		}

		foreach ($songIds as $songId) {
			foreach ($this->songTagRepository->findBySong($songId) as $songTag) {
				$result[$songId][] = $songTag->tag->id;
			}
			sort($result[$songId]);
		}
		return $result;
	}

	/**
	 * @param list<int> $tagIds
	 * @return array{added: list<int>, removed: list<int>}
	 */
	public function setTagsForSong(Workspace $workspace, Song $song, array $tagIds): array
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
		foreach ($this->songTagRepository->findBySong($song->id) as $songTag) {
			$existing[$songTag->tag->id] = $songTag;
		}

		$added = [];
		$removed = [];
		$now = new DateTimeImmutable();

		foreach ($desired as $tagId => $tag) {
			if (isset($existing[$tagId])) {
				continue;
			}
			$row = new SongTag(song: $song, tag: $tag);
			$row->createdAt = $now;
			$row->updatedAt = $now;
			$this->songTagRepository->persist($row);
			$added[] = $tagId;
		}

		foreach ($existing as $tagId => $row) {
			if (isset($desired[$tagId])) {
				continue;
			}
			$this->songTagRepository->delete($row);
			$removed[] = $tagId;
		}

		return ['added' => $added, 'removed' => $removed];
	}

	public function deleteAllForSong(Song $song): void
	{
		foreach ($this->songTagRepository->findBySong($song->id) as $row) {
			$this->songTagRepository->delete($row);
		}
	}
}
