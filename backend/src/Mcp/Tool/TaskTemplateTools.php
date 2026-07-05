<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use RuntimeException;
use Ukolio\Mcp\Dto\McpTaskDto;
use Ukolio\Mcp\Dto\McpTaskTemplateDto;
use Ukolio\Mcp\Dto\McpTaskTemplateListDto;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Mcp\Tool\Helper\StatusResolver;
use Ukolio\Model\Entity\Enum\StatusTypeEnum;
use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskTemplate;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Service\Auth\PermissionCheckerInterface;
use Ukolio\Service\Provider\ProjectProviderInterface;
use Ukolio\Service\Provider\TaskCodeResolverInterface;
use Ukolio\Service\Provider\TaskFieldValueProviderInterface;
use Ukolio\Service\Provider\TaskTagProviderInterface;
use Ukolio\Service\Provider\TaskTemplateProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;

final readonly class TaskTemplateTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private TaskTemplateProviderInterface $taskTemplateProvider,
		private ProjectProviderInterface $projectProvider,
		private TaskCodeResolverInterface $taskCodeResolver,
		private WorkspaceProviderInterface $workspaceProvider,
		private TaskFieldValueProviderInterface $taskFieldValueProvider,
		private TaskTagProviderInterface $taskTagProvider,
		private PermissionCheckerInterface $permissionChecker,
		private StatusResolver $statusResolver,
	) {
	}

	/**
	 * List the task templates saved in the current workspace.
	 */
	#[McpTool(name: 'list_task_templates', description: 'List task templates saved in the current workspace')]
	public function listTaskTemplates(): McpTaskTemplateListDto
	{
		$workspace = $this->requireWorkspace();

		$templates = [];
		foreach ($this->taskTemplateProvider->getTemplates($workspace) as $template) {
			$templates[] = McpTaskTemplateDto::fromEntity($template);
		}

		return new McpTaskTemplateListDto($templates);
	}

	/**
	 * Save an existing task as a reusable workspace template. The template captures the task's
	 * name, description, priority, custom-field values, and tags — not comments, files, or relations.
	 *
	 * @param int|string $taskId Task ID or code (e.g. "MP-3") to snapshot
	 * @param string $name Template name (unique per workspace)
	 */
	#[McpTool(name: 'save_task_as_template', description: 'Save a task as a reusable workspace template')]
	public function saveTaskAsTemplate(int|string $taskId, string $name): McpTaskTemplateDto
	{
		$task = $this->requireTask($taskId);
		$this->requireManageTemplates($task->project->workspace);

		$template = $this->taskTemplateProvider->createFromTask($task, $name);

		return McpTaskTemplateDto::fromEntity($template);
	}

	/**
	 * Create a new task in a project from a saved template. The task lands in the project's
	 * Start status unless statusId or statusName is given. Stale tag ids in the template are dropped;
	 * a stale priority falls back to the workspace default.
	 *
	 * @param int $templateId Template ID (see list_task_templates)
	 * @param int $projectId Target project ID
	 * @param string|null $name Optional name override; defaults to the template's task name
	 * @param int|null $statusId Optional explicit status ID
	 * @param string|null $statusName Optional status name (case-insensitive); ignored if statusId is given
	 */
	#[McpTool(name: 'create_task_from_template', description: 'Create a task in a project from a saved template')]
	public function createTaskFromTemplate(
		int $templateId,
		int $projectId,
		?string $name = null,
		?int $statusId = null,
		?string $statusName = null,
	): McpTaskDto {
		$user = $this->userContext->getUser();
		$workspace = $this->requireWorkspace();
		$template = $this->requireTemplate($workspace, $templateId);
		$project = $this->requireProject($workspace, $projectId);

		$status = $this->statusResolver->resolve($project, $statusId, $statusName)
			?? $this->statusResolver->findByType($project, StatusTypeEnum::Start)
			?? throw new RuntimeException(sprintf('No Start status found for project %d.', $projectId));

		$task = $this->taskTemplateProvider->instantiate($user, $template, $project, $status, $name);

		return McpTaskDto::fromEntity(
			$task,
			$this->taskFieldValueProvider->findByTask($task),
			$this->taskTagProvider->getTagIdsForTask($task),
		);
	}

	private function requireWorkspace(): Workspace
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->userContext->getUser());
		if ($workspace === null) {
			throw new RuntimeException('No active workspace.');
		}

		return $workspace;
	}

	private function requireProject(Workspace $workspace, int $projectId): Project
	{
		$project = $this->projectProvider->getProject($workspace, $projectId);
		if ($project === null) {
			throw new RuntimeException(sprintf('Project %d not found.', $projectId));
		}

		return $project;
	}

	private function requireTask(int|string $taskId): Task
	{
		$task = $this->taskCodeResolver->resolveForUser($this->userContext->getUser(), (string) $taskId);
		if ($task === null) {
			throw new RuntimeException(sprintf('Task "%s" not found.', (string) $taskId));
		}

		return $task;
	}

	private function requireTemplate(Workspace $workspace, int $templateId): TaskTemplate
	{
		$template = $this->taskTemplateProvider->getTemplate($workspace, $templateId);
		if ($template === null) {
			throw new RuntimeException(sprintf('Task template %d not found.', $templateId));
		}

		return $template;
	}

	private function requireManageTemplates(Workspace $workspace): void
	{
		if (!$this->permissionChecker->canManageTaskTemplates($this->userContext->getUser(), $workspace)) {
			throw new RuntimeException('You do not have permission to manage task templates.');
		}
	}
}
