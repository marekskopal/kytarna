<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use DateTimeImmutable;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\LectureTag;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Model\Repository\LectureTagRepository;
use Kytarna\Model\Repository\TagRepository;
use RuntimeException;

final readonly class LectureTagProvider implements LectureTagProviderInterface
{
	public function __construct(private LectureTagRepository $lectureTagRepository, private TagRepository $tagRepository,)
	{
	}

	/** @return list<int> */
	public function getTagIdsForLecture(Lecture $lecture): array
	{
		$ids = [];
		foreach ($this->lectureTagRepository->findByLecture($lecture->id) as $lectureTag) {
			$ids[] = $lectureTag->tag->id;
		}
		sort($ids);
		return $ids;
	}

	/**
	 * @param list<int> $lectureIds
	 * @return array<int, list<int>>
	 */
	public function getTagIdsByLectureIds(array $lectureIds): array
	{
		$result = [];
		foreach ($lectureIds as $lectureId) {
			$result[$lectureId] = [];
		}
		if ($lectureIds === []) {
			return $result;
		}

		foreach ($lectureIds as $lectureId) {
			foreach ($this->lectureTagRepository->findByLecture($lectureId) as $lectureTag) {
				$result[$lectureId][] = $lectureTag->tag->id;
			}
			sort($result[$lectureId]);
		}
		return $result;
	}

	/**
	 * @param list<int> $tagIds
	 * @return array{added: list<int>, removed: list<int>}
	 */
	public function setTagsForLecture(Workspace $workspace, Lecture $lecture, array $tagIds): array
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
		foreach ($this->lectureTagRepository->findByLecture($lecture->id) as $lectureTag) {
			$existing[$lectureTag->tag->id] = $lectureTag;
		}

		$added = [];
		$removed = [];
		$now = new DateTimeImmutable();

		foreach ($desired as $tagId => $tag) {
			if (isset($existing[$tagId])) {
				continue;
			}
			$row = new LectureTag(lecture: $lecture, tag: $tag);
			$row->createdAt = $now;
			$row->updatedAt = $now;
			$this->lectureTagRepository->persist($row);
			$added[] = $tagId;
		}

		foreach ($existing as $tagId => $row) {
			if (isset($desired[$tagId])) {
				continue;
			}
			$this->lectureTagRepository->delete($row);
			$removed[] = $tagId;
		}

		return ['added' => $added, 'removed' => $removed];
	}

	public function deleteAllForLecture(Lecture $lecture): void
	{
		foreach ($this->lectureTagRepository->findByLecture($lecture->id) as $row) {
			$this->lectureTagRepository->delete($row);
		}
	}
}
