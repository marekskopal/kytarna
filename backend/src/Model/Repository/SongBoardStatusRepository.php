<?php

declare(strict_types=1);

namespace Kytarna\Model\Repository;

use Iterator;
use Kytarna\Model\Entity\SongBoardStatus;
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<SongBoardStatus> */
final class SongBoardStatusRepository extends AbstractRepository
{
	public function findForUserAndSong(int $userId, int $songId): ?SongBoardStatus
	{
		return $this->findOne(['user_id' => $userId, 'song_id' => $songId]);
	}

	/** @return Iterator<SongBoardStatus> */
	public function findAllForUserInCourse(int $userId, int $courseId): Iterator
	{
		return $this->select()
			->where(['user_id' => $userId, 'song.course_id' => $courseId])
			->fetchAll();
	}

	/** @return Iterator<SongBoardStatus> */
	public function findAllForUserInWorkspace(int $userId, int $workspaceId): Iterator
	{
		return $this->select()
			->where(['user_id' => $userId, 'song.workspace_id' => $workspaceId])
			->fetchAll();
	}

	/** @return Iterator<SongBoardStatus> */
	public function findBySong(int $songId): Iterator
	{
		return $this->select()->where(['song_id' => $songId])->fetchAll();
	}
}
