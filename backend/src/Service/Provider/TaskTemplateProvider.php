<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use Iterator;
use RuntimeException;
use Ukolio\Dto\TaskTemplatePayloadDto;
use Ukolio\Model\Entity\Priority;
use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\Status;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskTemplate;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\TaskTemplateRepository;

final readonly class TaskTemplateProvider implements TaskTemplateProviderInterface
{
	private const int MaxNameLength = 80;

	public function __construct(
		private TaskTemplateRepository $taskTemplateRepository,
		private TaskProviderInterface $taskProvider,
		private TaskFieldValueProviderInterface $taskFieldValueProvider,
		private TaskTagProviderInterface $taskTagProvider,
		private TagProviderInterface $tagProvider,
		private PriorityProviderInterface $priorityProvider,
	) {
	}

	/** @return Iterator<TaskTemplate> */
	public function getTemplates(Workspace $workspace): Iterator
	{
		return $this->taskTemplateRepository->findByWorkspace($workspace->id);
	}

	public function getTemplate(Workspace $workspace, int $templateId): ?TaskTemplate
	{
		return $this->taskTemplateRepository->findOneByIdAndWorkspace($templateId, $workspace->id);
	}

	public function getTemplateById(int $templateId): ?TaskTemplate
	{
		return $this->taskTemplateRepository->findOneById($templateId);
	}

	public function createFromTask(Task $task, string $name): TaskTemplate
	{
		$workspace = $task->project->workspace;
		$name = $this->validateName($workspace, $name);

		$payload = TaskTemplatePayloadDto::fromTask(
			$task,
			$this->taskFieldValueProvider->findByTask($task),
			$this->taskTagProvider->getTagIdsForTask($task),
		);

		$now = new DateTimeImmutable();
		$template = new TaskTemplate(workspace: $workspace, name: $name, payload: $payload->toJson());
		$template->createdAt = $now;
		$template->updatedAt = $now;
		$this->taskTemplateRepository->persist($template);

		return $template;
	}

	public function deleteTemplate(TaskTemplate $template): void
	{
		$this->taskTemplateRepository->delete($template);
	}

	public function instantiate(User $author, TaskTemplate $template, Project $project, Status $status, ?string $name = null): Task
	{
		if ($project->workspace->id !== $template->workspace->id) {
			throw new RuntimeException('Template and project belong to different workspaces.');
		}

		$payload = TaskTemplatePayloadDto::fromJson($template->payload);

		$priority = $this->resolvePriority($template->workspace, $payload->priorityId);

		// Tags may have been deleted since the template was saved — silently drop stale ids.
		$tagIds = array_values(array_filter(
			$payload->tagIds,
			fn (int $tagId): bool => $this->tagProvider->getTag($template->workspace, $tagId) !== null,
		));

		return $this->taskProvider->createTask(
			author: $author,
			project: $project,
			status: $status,
			name: $name ?? $payload->name,
			description: $payload->description,
			priority: $priority,
			dueDate: null,
			assignee: $author,
			fieldValues: $payload->fieldValuesMap(),
			tagIds: $tagIds,
		);
	}

	/** A stale priority id falls back to the workspace default. */
	private function resolvePriority(Workspace $workspace, ?int $priorityId): Priority
	{
		$priority = $priorityId !== null ? $this->priorityProvider->getPriority($workspace, $priorityId) : null;

		return $priority
			?? $this->priorityProvider->getDefaultForWorkspace($workspace)
			?? throw new RuntimeException('Workspace has no priorities configured.');
	}

	private function validateName(Workspace $workspace, string $name): string
	{
		$trimmed = trim($name);
		if ($trimmed === '') {
			throw new RuntimeException('Template name cannot be empty.');
		}
		if (mb_strlen($trimmed) > self::MaxNameLength) {
			throw new RuntimeException(sprintf('Template name is too long (max %d characters).', self::MaxNameLength));
		}

		$existing = $this->taskTemplateRepository->findOneByWorkspaceAndName($workspace->id, $trimmed);
		if ($existing !== null) {
			throw new RuntimeException('A template with the name "' . $trimmed . '" already exists.');
		}

		return $trimmed;
	}
}
