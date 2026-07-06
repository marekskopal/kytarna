<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\Workspace;

interface SongTagProviderInterface
{
	/** @return list<int> */
	public function getTagIdsForSong(Song $song): array;

	/**
	 * @param list<int> $songIds
	 * @return array<int, list<int>> song id => list of tag ids
	 */
	public function getTagIdsBySongIds(array $songIds): array;

	/**
	 * Replace the set of tags applied to a song with the given list.
	 *
	 * @param list<int> $tagIds
	 * @return array{added: list<int>, removed: list<int>}
	 */
	public function setTagsForSong(Workspace $workspace, Song $song, array $tagIds): array;

	public function deleteAllForSong(Song $song): void;
}
