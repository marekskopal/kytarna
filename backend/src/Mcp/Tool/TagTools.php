<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Tool;

use Kytarna\Mcp\Dto\McpLectureDto;
use Kytarna\Mcp\Dto\McpTagDto;
use Kytarna\Mcp\Dto\McpTagListDto;
use Kytarna\Mcp\McpUserContextInterface;
use Kytarna\Model\Entity\Enum\EventTypeEnum;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\Tag;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Service\Auth\PermissionCheckerInterface;
use Kytarna\Service\Provider\EventProviderInterface;
use Kytarna\Service\Provider\LectureProviderInterface;
use Kytarna\Service\Provider\LectureTagProviderInterface;
use Kytarna\Service\Provider\TagProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Mcp\Capability\Attribute\McpTool;
use RuntimeException;

final readonly class TagTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private TagProviderInterface $tagProvider,
		private LectureTagProviderInterface $lectureTagProvider,
		private LectureProviderInterface $lectureProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
		private EventProviderInterface $eventProvider,
	) {
	}

	/** List all tags in the current workspace. */
	#[McpTool(name: 'list_workspace_tags', description: 'List tags defined in the current workspace')]
	public function listWorkspaceTags(): McpTagListDto
	{
		$workspace = $this->requireWorkspace();
		$tags = [];
		foreach ($this->tagProvider->getTags($workspace) as $tag) {
			$tags[] = McpTagDto::fromEntity($tag);
		}
		return new McpTagListDto($tags);
	}

	/**
	 * Find a tag by case-insensitive name match in the current workspace.
	 *
	 * @param string $name Tag name to look up
	 */
	#[McpTool(name: 'find_tag_by_name', description: 'Find a tag in the current workspace by name (case-insensitive)')]
	public function findTagByName(string $name): ?McpTagDto
	{
		$workspace = $this->requireWorkspace();
		$needle = mb_strtolower(trim($name));
		foreach ($this->tagProvider->getTags($workspace) as $tag) {
			if (mb_strtolower($tag->name) === $needle) {
				return McpTagDto::fromEntity($tag);
			}
		}
		return null;
	}

	/**
	 * Create a new tag in the current workspace.
	 *
	 * @param string $name Tag name (unique per workspace)
	 * @param string $color Hex color, e.g. "#3b82f6"
	 */
	#[McpTool(name: 'create_tag', description: 'Create a new tag in the current workspace')]
	public function createTag(string $name, string $color): McpTagDto
	{
		$workspace = $this->requireWorkspace();
		$this->requireManageTags($workspace);

		$tag = $this->tagProvider->createTag(
			author: $this->userContext->getUser(),
			workspace: $workspace,
			name: $name,
			color: $color,
		);

		return McpTagDto::fromEntity($tag);
	}

	/**
	 * Update an existing tag in the current workspace.
	 *
	 * @param int $tagId Tag ID
	 * @param string|null $name New name
	 * @param string|null $color New hex color
	 */
	#[McpTool(name: 'update_tag', description: 'Update a tag in the current workspace')]
	public function updateTag(int $tagId, ?string $name = null, ?string $color = null): McpTagDto
	{
		$workspace = $this->requireWorkspace();
		$this->requireManageTags($workspace);
		$tag = $this->requireTag($tagId);

		$updated = $this->tagProvider->updateTag(
			author: $this->userContext->getUser(),
			tag: $tag,
			name: $name ?? $tag->name,
			color: $color ?? $tag->color,
		);

		return McpTagDto::fromEntity($updated);
	}

	/**
	 * Delete a tag. Detaches it from every lecture that referenced it.
	 *
	 * @param int $tagId Tag ID
	 */
	#[McpTool(name: 'delete_tag', description: 'Delete a tag (detaches from all lectures)')]
	public function deleteTag(int $tagId): string
	{
		$workspace = $this->requireWorkspace();
		$this->requireManageTags($workspace);
		$tag = $this->requireTag($tagId);

		$this->tagProvider->deleteTag($this->userContext->getUser(), $tag);

		return 'Tag deleted.';
	}

	/**
	 * Replace the full set of tags applied to a lecture with the given IDs. Pass [] to clear.
	 *
	 * @param int $lectureId Lecture ID
	 * @param list<int> $tagIds Tag IDs (must all belong to the same workspace as the lecture)
	 */
	#[McpTool(name: 'set_lecture_tags', description: 'Replace the set of tags applied to a lecture')]
	public function setLectureTags(int $lectureId, array $tagIds): McpLectureDto
	{
		$lecture = $this->requireLecture($lectureId);
		$workspace = $lecture->course->workspace;

		$tagChanges = $this->lectureTagProvider->setTagsForLecture($workspace, $lecture, $tagIds);

		if ($tagChanges['added'] !== [] || $tagChanges['removed'] !== []) {
			$this->eventProvider->recordEvent(
				$this->userContext->getUser(),
				$lecture->course,
				EventTypeEnum::LectureTagsUpdated,
				['lectureName' => $lecture->name, 'added' => $tagChanges['added'], 'removed' => $tagChanges['removed']],
				$lecture->id,
			);
		}

		return McpLectureDto::fromEntity($lecture, $this->lectureTagProvider->getTagIdsForLecture($lecture));
	}

	private function requireWorkspace(): Workspace
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->userContext->getUser());
		if ($workspace === null) {
			throw new RuntimeException('No active workspace.');
		}
		return $workspace;
	}

	private function requireTag(int $tagId): Tag
	{
		$workspace = $this->requireWorkspace();
		$tag = $this->tagProvider->getTag($workspace, $tagId);
		if ($tag === null) {
			throw new RuntimeException(sprintf('Tag %d not found.', $tagId));
		}
		return $tag;
	}

	private function requireLecture(int $lectureId): Lecture
	{
		$lecture = $this->lectureProvider->getLecture($lectureId);
		if ($lecture === null || !$this->workspaceProvider->isMember($this->userContext->getUser(), $lecture->course->workspace)) {
			throw new RuntimeException(sprintf('Lecture %d not found.', $lectureId));
		}
		return $lecture;
	}

	private function requireManageTags(Workspace $workspace): void
	{
		if (!$this->permissionChecker->canManageTags($this->userContext->getUser(), $workspace)) {
			throw new RuntimeException('You do not have permission to manage tags.');
		}
	}
}
