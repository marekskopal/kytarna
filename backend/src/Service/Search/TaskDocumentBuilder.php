<?php

declare(strict_types=1);

namespace Ukolio\Service\Search;

use Ukolio\Model\Entity\Enum\FieldTypeEnum;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Repository\TaskCommentRepository;
use Ukolio\Model\Repository\TaskFieldValueRepository;
use Ukolio\Model\Repository\TaskTagRepository;

final readonly class TaskDocumentBuilder
{
	public function __construct(
		private TaskCommentRepository $taskCommentRepository,
		private TaskFieldValueRepository $taskFieldValueRepository,
		private TaskTagRepository $taskTagRepository,
	) {
	}

	/** @return array<string, mixed> */
	public function build(Task $task): array
	{
		$comments = [];
		foreach ($this->taskCommentRepository->findByTask($task->id) as $comment) {
			$comments[] = $comment->body;
		}

		$fieldValues = [];
		foreach ($this->taskFieldValueRepository->findByTask($task->id) as $value) {
			if ($value->value === null || $value->value === '') {
				continue;
			}
			if ($value->field->type !== FieldTypeEnum::Text && $value->field->type !== FieldTypeEnum::Textarea) {
				continue;
			}
			$fieldValues[] = $value->value;
		}

		$tags = [];
		foreach ($this->taskTagRepository->findByTask($task->id) as $taskTag) {
			$tags[] = $taskTag->tag->name;
		}

		return [
			'id' => $task->id,
			'code' => $task->project->prefix . '-' . $task->sequenceNumber,
			'workspaceId' => $task->project->workspace->id,
			'projectId' => $task->project->id,
			'statusId' => $task->status->id,
			'statusType' => $task->status->type->value,
			'priorityId' => $task->priority->id,
			'assigneeId' => $task->assignee?->id,
			'name' => $task->name,
			'description' => $task->description,
			'comments' => $comments,
			'fieldValues' => $fieldValues,
			'tags' => $tags,
			'createdAt' => $task->createdAt->getTimestamp(),
			'updatedAt' => $task->updatedAt->getTimestamp(),
		];
	}
}
