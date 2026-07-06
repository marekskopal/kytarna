<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Tool;

use Kytarna\Mcp\Dto\McpStatusDto;
use Kytarna\Mcp\Dto\McpStatusListDto;
use Kytarna\Mcp\McpUserContextInterface;
use Kytarna\Model\Entity\Enum\StatusTypeEnum;
use Kytarna\Model\Entity\Status;
use Kytarna\Model\Entity\Workflow;
use Kytarna\Service\Auth\PermissionCheckerInterface;
use Kytarna\Service\Provider\CourseProviderInterface;
use Kytarna\Service\Provider\StatusProviderInterface;
use Kytarna\Service\Provider\WorkflowProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Mcp\Capability\Attribute\McpTool;
use RuntimeException;

final readonly class WorkflowTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private CourseProviderInterface $courseProvider,
		private WorkflowProviderInterface $workflowProvider,
		private StatusProviderInterface $statusProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
	) {
	}

	/**
	 * List all statuses (Kanban columns) for a course's workflow, ordered by position.
	 * Each status has a type: Start, Normal, or Finish — Start is the initial column for new lectures,
	 * Finish marks completion. The default workflow is "To Learn" (Start), "Learning" (Normal), "Mastered" (Finish).
	 *
	 * @param int $courseId Course ID
	 */
	#[McpTool(name: 'list_statuses', description: 'List workflow statuses (columns) for a course, ordered by position')]
	public function listStatuses(int $courseId): McpStatusListDto
	{
		$workflow = $this->resolveWorkflow($courseId);

		$statuses = [];
		foreach ($this->statusProvider->getStatuses($workflow) as $status) {
			$statuses[] = McpStatusDto::fromEntity($status);
		}

		return new McpStatusListDto($statuses);
	}

	/**
	 * Find a status in a course's workflow by name (case-insensitive, exact match). Returns null if not found.
	 *
	 * @param int $courseId Course ID
	 * @param string $name Status name to look up (e.g. "Learning")
	 */
	#[McpTool(name: 'find_status_by_name', description: 'Find a workflow status by name within a course (case-insensitive, exact match)')]
	public function findStatusByName(int $courseId, string $name): ?McpStatusDto
	{
		$workflow = $this->resolveWorkflow($courseId);

		$needle = mb_strtolower($name);
		foreach ($this->statusProvider->getStatuses($workflow) as $status) {
			if (mb_strtolower($status->name) === $needle) {
				return McpStatusDto::fromEntity($status);
			}
		}

		return null;
	}

	/**
	 * Create a new status (Kanban column) in a course's workflow.
	 *
	 * @param int $courseId Course ID
	 * @param string $name Column name (e.g. "In Review")
	 * @param string $type Status type: Start, Normal, or Finish. A workflow needs exactly one Start and at least one Finish.
	 * @param string $color Hex color (e.g. "#a855f7")
	 * @param int|null $position Zero-based insertion index; appended to the end if null
	 */
	#[McpTool(name: 'create_status', description: 'Create a new workflow status (Kanban column) in a course')]
	public function createStatus(int $courseId, string $name, string $type, string $color = '#94a3b8', ?int $position = null,): McpStatusDto
	{
		$workflow = $this->resolveWorkflowForManagement($courseId);

		$status = $this->statusProvider->createStatus(
			workflow: $workflow,
			name: $name,
			color: $color,
			type: $this->parseType($type),
			position: $position,
		);

		return McpStatusDto::fromEntity($status);
	}

	/**
	 * Update a status (rename, recolor, or change its type). Omitted parameters are left unchanged.
	 * Use move_status to change a status's position within the workflow.
	 *
	 * @param int $statusId Status ID
	 * @param string|null $name New name
	 * @param string|null $type New type: Start, Normal, or Finish
	 * @param string|null $color New hex color
	 */
	#[McpTool(name: 'update_status', description: 'Update a workflow status (name, type, color)')]
	public function updateStatus(int $statusId, ?string $name = null, ?string $type = null, ?string $color = null): McpStatusDto
	{
		$status = $this->resolveStatusForManagement($statusId);

		$updated = $this->statusProvider->updateStatus(
			status: $status,
			name: $name ?? $status->name,
			color: $color ?? $status->color,
			type: $type !== null ? $this->parseType($type) : $status->type,
		);

		return McpStatusDto::fromEntity($updated);
	}

	/**
	 * Move a status to a new zero-based position within its workflow. Sibling positions are shifted as needed.
	 *
	 * @param int $statusId Status ID
	 * @param int $position New zero-based position
	 */
	#[McpTool(name: 'move_status', description: 'Reorder a workflow status to a new position')]
	public function moveStatus(int $statusId, int $position): McpStatusDto
	{
		$status = $this->resolveStatusForManagement($statusId);
		$moved = $this->statusProvider->moveStatus($status, $position);

		return McpStatusDto::fromEntity($moved);
	}

	/**
	 * Delete a status. Cannot delete the last remaining status of a workflow. Lectures currently in the status must be moved first.
	 *
	 * @param int $statusId Status ID
	 */
	#[McpTool(name: 'delete_status', description: 'Delete a workflow status (must not be the last one in its workflow)')]
	public function deleteStatus(int $statusId): string
	{
		$status = $this->resolveStatusForManagement($statusId);

		$siblings = iterator_to_array($this->statusProvider->getStatuses($status->workflow), false);
		if (count($siblings) <= 1) {
			throw new RuntimeException('Cannot delete the last status of a workflow.');
		}

		$this->statusProvider->deleteStatus($status);

		return 'Status deleted.';
	}

	private function resolveWorkflow(int $courseId): Workflow
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->userContext->getUser());
		if ($workspace === null) {
			throw new RuntimeException('No active workspace.');
		}

		$course = $this->courseProvider->getCourse($workspace, $courseId);
		if ($course === null) {
			throw new RuntimeException(sprintf('Course %d not found.', $courseId));
		}

		$workflow = $this->workflowProvider->getWorkflowByCourse($course);
		if ($workflow === null) {
			throw new RuntimeException(sprintf('Workflow for course %d not found.', $courseId));
		}

		return $workflow;
	}

	private function resolveWorkflowForManagement(int $courseId): Workflow
	{
		$workflow = $this->resolveWorkflow($courseId);
		if (!$this->permissionChecker->canManageCourses($this->userContext->getUser(), $workflow->course->workspace)) {
			throw new RuntimeException('You do not have permission to manage workflow statuses.');
		}
		return $workflow;
	}

	private function resolveStatusForManagement(int $statusId): Status
	{
		$status = $this->statusProvider->getStatus($statusId);
		if ($status === null) {
			throw new RuntimeException(sprintf('Status %d not found.', $statusId));
		}

		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->userContext->getUser());
		if ($workspace === null || $status->workflow->course->workspace->id !== $workspace->id) {
			throw new RuntimeException(sprintf('Status %d not found.', $statusId));
		}

		if (!$this->permissionChecker->canManageCourses($this->userContext->getUser(), $workspace)) {
			throw new RuntimeException('You do not have permission to manage workflow statuses.');
		}

		return $status;
	}

	private function parseType(string $type): StatusTypeEnum
	{
		return StatusTypeEnum::tryFrom($type) ?? throw new RuntimeException(
			sprintf('Invalid status type "%s". Expected Start, Normal, or Finish.', $type),
		);
	}
}
