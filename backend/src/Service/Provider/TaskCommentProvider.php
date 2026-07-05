<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use DateTimeImmutable;
use RuntimeException;
use Kytario\Model\Entity\Enum\EventTypeEnum;
use Kytario\Model\Entity\Task;
use Kytario\Model\Entity\TaskComment;
use Kytario\Model\Entity\User;
use Kytario\Model\Repository\TaskCommentRepository;
use Kytario\Service\Actor\ActorContextInterface;
use Kytario\Service\Search\SearchIndexer;

final readonly class TaskCommentProvider implements TaskCommentProviderInterface
{
	private const int MaxBodyLength = 10000;

	/** Bound the notification fan-out: at most this many distinct members are mentioned per comment. */
	private const int MaxMentions = 50;

	/** Mention token embedded in the body: @[Display Name](user:42). */
	private const string MentionPattern = '/@\[[^\]]+\]\(user:(\d+)\)/';

	public function __construct(
		private TaskCommentRepository $taskCommentRepository,
		private EventProviderInterface $eventProvider,
		private ActorContextInterface $actorContext,
		private SearchIndexer $searchIndexer,
		private WorkspaceProviderInterface $workspaceProvider,
	) {
	}

	/** @return list<TaskComment> */
	public function findByTask(Task $task): array
	{
		$result = [];
		foreach ($this->taskCommentRepository->findByTask($task->id) as $comment) {
			$result[] = $comment;
		}
		return $result;
	}

	public function getComment(int $commentId): ?TaskComment
	{
		return $this->taskCommentRepository->findOneById($commentId);
	}

	public function createComment(User $author, Task $task, string $body, ?TaskComment $parent = null): TaskComment
	{
		$trimmed = $this->validateBody($body);

		if ($parent !== null && $parent->task->id !== $task->id) {
			throw new RuntimeException('Parent comment belongs to a different task.');
		}

		// Clamp threads to a single level: a reply to a reply attaches to the same top-level comment.
		$parentCommentId = null;
		if ($parent !== null) {
			$parentCommentId = $parent->parentCommentId ?? $parent->id;
		}

		$now = new DateTimeImmutable();
		$comment = new TaskComment(
			task: $task,
			author: $author,
			body: $trimmed,
			actorType: $this->actorContext->getActorType(),
			mcpClientId: $this->actorContext->getMcpClientId(),
			mcpClientName: $this->actorContext->getMcpClientName(),
			parentCommentId: $parentCommentId,
		);
		$comment->createdAt = $now;
		$comment->updatedAt = $now;

		$this->taskCommentRepository->persist($comment);

		$this->eventProvider->recordEvent(
			$author,
			$task->project,
			EventTypeEnum::TaskCommentAdded,
			[
				'commentId' => $comment->id,
				'taskId' => $task->id,
				'mentionedUserIds' => $this->extractMentionedUserIds($trimmed, $task),
			],
			$task->id,
		);

		$this->searchIndexer->queueUpsert($task->id);

		return $comment;
	}

	public function updateComment(User $editor, TaskComment $comment, string $body): TaskComment
	{
		$trimmed = $this->validateBody($body);

		$task = $comment->task;
		$now = new DateTimeImmutable();
		$comment->body = $trimmed;
		$comment->editedAt = $now;
		$comment->updatedAt = $now;
		$this->taskCommentRepository->persist($comment);

		$this->eventProvider->recordEvent(
			$editor,
			$task->project,
			EventTypeEnum::TaskCommentEdited,
			[
				'commentId' => $comment->id,
				'taskId' => $task->id,
				'mentionedUserIds' => $this->extractMentionedUserIds($trimmed, $task),
			],
			$task->id,
		);

		$this->searchIndexer->queueUpsert($task->id);

		return $comment;
	}

	public function deleteComment(User $author, TaskComment $comment): void
	{
		$task = $comment->task;

		// Threads are single-level, so a top-level comment's replies must go first (FK restricts otherwise).
		foreach ($this->taskCommentRepository->findReplies($comment->id) as $reply) {
			$this->taskCommentRepository->delete($reply);
		}

		$this->taskCommentRepository->delete($comment);

		$this->eventProvider->recordEvent(
			$author,
			$task->project,
			EventTypeEnum::TaskCommentDeleted,
			['commentId' => $comment->id, 'taskId' => $task->id],
			$task->id,
		);

		$this->searchIndexer->queueUpsert($task->id);
	}

	private function validateBody(string $body): string
	{
		$trimmed = trim($body);
		if ($trimmed === '') {
			throw new RuntimeException('Comment body is empty.');
		}
		if (mb_strlen($trimmed) > self::MaxBodyLength) {
			throw new RuntimeException(sprintf('Comment is too long (max %d characters).', self::MaxBodyLength));
		}
		return $trimmed;
	}

	/**
	 * Parse @[Name](user:ID) tokens and keep only ids that are real members of the task's
	 * workspace. Recorded on the event so the notifications work (U-83) can fan out pings.
	 *
	 * @return list<int>
	 */
	private function extractMentionedUserIds(string $body, Task $task): array
	{
		if (preg_match_all(self::MentionPattern, $body, $matches) === 0) {
			return [];
		}

		$candidateIds = array_values(array_unique(array_map('intval', $matches[1])));

		$memberIds = [];
		foreach ($this->workspaceProvider->getMembers($task->project->workspace) as $membership) {
			$memberIds[$membership->user->id] = true;
		}

		$mentioned = array_values(array_filter($candidateIds, static fn (int $id): bool => isset($memberIds[$id])));

		// Bound the fan-out so a single comment can't mass-notify the whole workspace repeatedly.
		return array_slice($mentioned, 0, self::MaxMentions);
	}
}
