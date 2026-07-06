<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\Workspace;

interface LectureTagProviderInterface
{
	/** @return list<int> */
	public function getTagIdsForLecture(Lecture $lecture): array;

	/**
	 * @param list<int> $lectureIds
	 * @return array<int, list<int>> lecture id => list of tag ids
	 */
	public function getTagIdsByLectureIds(array $lectureIds): array;

	/**
	 * Replace the set of tags applied to a lecture with the given list.
	 *
	 * @param list<int> $tagIds
	 * @return array{added: list<int>, removed: list<int>}
	 */
	public function setTagsForLecture(Workspace $workspace, Lecture $lecture, array $tagIds): array;

	public function deleteAllForLecture(Lecture $lecture): void;
}
