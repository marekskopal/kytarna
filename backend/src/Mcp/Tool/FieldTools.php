<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use RuntimeException;
use Ukolio\Mcp\Dto\McpFieldDto;
use Ukolio\Mcp\Dto\McpFieldListDto;
use Ukolio\Mcp\Dto\McpProjectFieldDto;
use Ukolio\Mcp\Dto\McpProjectFieldListDto;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Model\Entity\Enum\FieldTypeEnum;
use Ukolio\Model\Entity\Field;
use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Service\Auth\PermissionCheckerInterface;
use Ukolio\Service\Provider\FieldProviderInterface;
use Ukolio\Service\Provider\ProjectFieldProviderInterface;
use Ukolio\Service\Provider\ProjectProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;

final readonly class FieldTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private FieldProviderInterface $fieldProvider,
		private ProjectFieldProviderInterface $projectFieldProvider,
		private ProjectProviderInterface $projectProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
	) {
	}

	/** List all custom fields defined in the current workspace's catalog. */
	#[McpTool(name: 'list_workspace_fields', description: 'List custom fields in the current workspace catalog')]
	public function listWorkspaceFields(): McpFieldListDto
	{
		$workspace = $this->requireWorkspace();
		$fields = [];
		foreach ($this->fieldProvider->getFields($workspace) as $field) {
			$fields[] = McpFieldDto::fromEntity($field);
		}
		return new McpFieldListDto($fields);
	}

	/**
	 * Get a single workspace field by ID.
	 *
	 * @param int $fieldId Field ID
	 */
	#[McpTool(name: 'get_field', description: 'Get a single custom field by ID')]
	public function getField(int $fieldId): McpFieldDto
	{
		return McpFieldDto::fromEntity($this->requireField($fieldId));
	}

	/**
	 * Create a new custom field in the workspace catalog.
	 *
	 * @param string $name Field name (must be unique within workspace)
	 * @param string $type Field type: Text, Textarea, Select, or Version
	 * @param bool $required Whether the field is required when saving a task
	 * @param string|null $defaultValue Optional default. For Select/Version it must be one of the options.
	 * @param list<string>|null $options Required for Select/Version. For Version every option must be valid semver.
	 */
	#[McpTool(name: 'create_field', description: 'Create a custom field in the workspace catalog')]
	public function createField(
		string $name,
		string $type,
		bool $required = false,
		?string $defaultValue = null,
		?array $options = null,
	): McpFieldDto {
		$workspace = $this->requireWorkspace();
		$this->requireManageFields($workspace);

		$field = $this->fieldProvider->createField(
			author: $this->userContext->getUser(),
			workspace: $workspace,
			name: $name,
			type: $this->parseType($type),
			required: $required,
			defaultValue: $defaultValue,
			options: $options,
		);

		return McpFieldDto::fromEntity($field);
	}

	/**
	 * Update a workspace field.
	 *
	 * @param int $fieldId Field ID
	 * @param string|null $name New name
	 * @param string|null $type New type: Text, Textarea, Select, or Version
	 * @param bool|null $required Required flag
	 * @param string|null $defaultValue New default value
	 * @param list<string>|null $options New options (Select/Version)
	 */
	#[McpTool(name: 'update_field', description: 'Update a custom field in the workspace catalog')]
	public function updateField(
		int $fieldId,
		?string $name = null,
		?string $type = null,
		?bool $required = null,
		?string $defaultValue = null,
		?array $options = null,
	): McpFieldDto {
		$workspace = $this->requireWorkspace();
		$this->requireManageFields($workspace);
		$field = $this->requireField($fieldId);

		$updated = $this->fieldProvider->updateField(
			author: $this->userContext->getUser(),
			field: $field,
			name: $name ?? $field->name,
			type: $type !== null ? $this->parseType($type) : $field->type,
			required: $required ?? $field->required,
			defaultValue: $defaultValue,
			options: $options,
		);

		return McpFieldDto::fromEntity($updated);
	}

	/**
	 * Delete a custom field. Removes it from every project and clears all task values.
	 *
	 * @param int $fieldId Field ID
	 */
	#[McpTool(name: 'delete_field', description: 'Delete a custom field (clears values from all tasks)')]
	public function deleteField(int $fieldId): string
	{
		$workspace = $this->requireWorkspace();
		$this->requireManageFields($workspace);
		$field = $this->requireField($fieldId);

		$this->fieldProvider->deleteField($this->userContext->getUser(), $field);

		return 'Field deleted.';
	}

	/**
	 * List the custom fields attached to a project (ordered by position).
	 *
	 * @param int $projectId Project ID
	 */
	#[McpTool(name: 'list_project_fields', description: 'List custom fields attached to a project')]
	public function listProjectFields(int $projectId): McpProjectFieldListDto
	{
		$project = $this->requireProject($projectId);
		$dtos = [];
		foreach ($this->projectFieldProvider->getProjectFields($project) as $pf) {
			$dtos[] = McpProjectFieldDto::fromEntity($pf);
		}
		return new McpProjectFieldListDto($dtos);
	}

	/**
	 * Replace the set of custom fields attached to a project. The given order becomes the display order.
	 *
	 * @param int $projectId Project ID
	 * @param list<int> $fieldIds Field IDs in display order; pass [] to detach all
	 */
	#[McpTool(name: 'set_project_fields', description: 'Attach (or replace) the set of custom fields for a project')]
	public function setProjectFields(int $projectId, array $fieldIds): McpProjectFieldListDto
	{
		$workspace = $this->requireWorkspace();
		$project = $this->requireProject($projectId);
		if (!$this->permissionChecker->canManageProjects($this->userContext->getUser(), $workspace)) {
			throw new RuntimeException('You do not have permission to manage this project.');
		}

		$this->projectFieldProvider->setProjectFields($this->userContext->getUser(), $project, $fieldIds);

		$dtos = [];
		foreach ($this->projectFieldProvider->getProjectFields($project) as $pf) {
			$dtos[] = McpProjectFieldDto::fromEntity($pf);
		}
		return new McpProjectFieldListDto($dtos);
	}

	private function requireWorkspace(): Workspace
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->userContext->getUser());
		if ($workspace === null) {
			throw new RuntimeException('No active workspace.');
		}
		return $workspace;
	}

	private function requireField(int $fieldId): Field
	{
		$workspace = $this->requireWorkspace();
		$field = $this->fieldProvider->getField($workspace, $fieldId);
		if ($field === null) {
			throw new RuntimeException(sprintf('Field %d not found.', $fieldId));
		}
		return $field;
	}

	private function requireProject(int $projectId): Project
	{
		$workspace = $this->requireWorkspace();
		$project = $this->projectProvider->getProject($workspace, $projectId);
		if ($project === null) {
			throw new RuntimeException(sprintf('Project %d not found.', $projectId));
		}
		return $project;
	}

	private function requireManageFields(Workspace $workspace): void
	{
		if (!$this->permissionChecker->canManageFields($this->userContext->getUser(), $workspace)) {
			throw new RuntimeException('You do not have permission to manage workspace fields.');
		}
	}

	private function parseType(string $type): FieldTypeEnum
	{
		$enum = FieldTypeEnum::tryFrom($type);
		if ($enum === null) {
			throw new RuntimeException(sprintf(
				'Invalid field type "%s". Valid values: %s',
				$type,
				implode(', ', array_column(FieldTypeEnum::cases(), 'value')),
			));
		}
		return $enum;
	}
}
