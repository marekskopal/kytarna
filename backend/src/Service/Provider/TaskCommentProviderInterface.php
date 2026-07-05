<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskComment;
use Ukolio\Model\Entity\User;

interface TaskCommentProviderInterface
{
	/** @return list<TaskComment> */
	public function findByTask(Task $task): array;

	public function getComment(int $commentId): ?TaskComment;

	public function createComment(User $author, Task $task, string $body, ?TaskComment $parent = null): TaskComment;

	public function updateComment(User $editor, TaskComment $comment, string $body): TaskComment;

	public function deleteComment(User $author, TaskComment $comment): void;
}
