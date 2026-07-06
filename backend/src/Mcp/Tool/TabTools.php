<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Tool;

use Kytarna\Mcp\Dto\McpTabDto;
use Kytarna\Mcp\Dto\McpTabListDto;
use Kytarna\Mcp\Dto\McpTabResultDto;
use Kytarna\Mcp\McpUserContextInterface;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\Tab;
use Kytarna\Service\Provider\LectureProviderInterface;
use Kytarna\Service\Provider\TabProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Kytarna\Service\Tab\Exception\TabValidationException;
use Mcp\Capability\Attribute\McpTool;
use RuntimeException;

final readonly class TabTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private LectureProviderInterface $lectureProvider,
		private TabProviderInterface $tabProvider,
		private WorkspaceProviderInterface $workspaceProvider,
	) {
	}

	/**
	 * List the tabs attached to a lecture (alphaTex + extracted metadata).
	 *
	 * @param int $lectureId Lecture ID
	 */
	#[McpTool(name: 'list_tabs', description: 'List the tabs (alphaTex notation) attached to a lecture.')]
	public function listTabs(int $lectureId): McpTabListDto
	{
		$lecture = $this->requireLecture($lectureId);
		$tabs = array_map(
			static fn (Tab $tab): McpTabDto => McpTabDto::fromEntity($tab),
			$this->tabProvider->getTabsByLecture($lecture),
		);
		return new McpTabListDto($tabs);
	}

	/**
	 * Get a single tab, including its full alphaTex content and extracted metadata (tempo, tuning, track count).
	 *
	 * @param int $tabId Tab ID
	 */
	#[McpTool(name: 'get_tab', description: 'Get a tab with its alphaTex content and metadata.')]
	public function getTab(int $tabId): McpTabDto
	{
		return McpTabDto::fromEntity($this->requireTab($tabId));
	}

	/**
	 * Create a tab from alphaTex. The alphaTex is validated by the tab-service before saving; if it is
	 * invalid, the result has valid=false and lists the errors (with line/col) so you can fix and retry.
	 *
	 * @param int $lectureId Lecture ID
	 * @param string $name Tab name (e.g. "Main riff")
	 * @param string $alphaTex The tab content in alphaTex notation
	 */
	#[McpTool(name: 'create_tab', description: 'Create a tab from alphaTex (validated before saving; returns errors if invalid).')]
	public function createTab(int $lectureId, string $name, string $alphaTex): McpTabResultDto
	{
		$user = $this->userContext->getUser();
		$lecture = $this->requireLecture($lectureId);

		try {
			$tab = $this->tabProvider->createTab($user, $lecture, $name, $alphaTex);
		} catch (TabValidationException $e) {
			return McpTabResultDto::fromValidationException($e);
		}

		return McpTabResultDto::success(McpTabDto::fromEntity($tab));
	}

	/**
	 * Update a tab's name and alphaTex content. The alphaTex is validated by the tab-service before saving;
	 * if it is invalid, the result has valid=false and lists the errors so you can fix and retry.
	 *
	 * @param int $tabId Tab ID
	 * @param string $name New tab name
	 * @param string $alphaTex New alphaTex content
	 */
	#[McpTool(name: 'update_tab', description: 'Update a tab (validated before saving; returns errors if invalid).')]
	public function updateTab(int $tabId, string $name, string $alphaTex): McpTabResultDto
	{
		$user = $this->userContext->getUser();
		$tab = $this->requireTab($tabId);

		try {
			$updated = $this->tabProvider->updateTab($user, $tab, $name, $alphaTex);
		} catch (TabValidationException $e) {
			return McpTabResultDto::fromValidationException($e);
		}

		return McpTabResultDto::success(McpTabDto::fromEntity($updated));
	}

	/**
	 * Delete a tab.
	 *
	 * @param int $tabId Tab ID
	 */
	#[McpTool(name: 'delete_tab', description: 'Delete a tab (irreversible).')]
	public function deleteTab(int $tabId): string
	{
		$user = $this->userContext->getUser();
		$tab = $this->requireTab($tabId);
		$this->tabProvider->deleteTab($user, $tab);
		return 'Tab deleted.';
	}

	/**
	 * Import a Guitar Pro file (gp3–gp8 / MusicXML). The base64-encoded bytes are stored in S3 and converted
	 * to alphaTex by the tab-service; a new tab (sourceType=imported_gp) is created with the converted content
	 * and extracted metadata. If the file cannot be parsed, the result has valid=false and lists the errors.
	 *
	 * @param int $lectureId Lecture ID
	 * @param string $name Tab name
	 * @param string $contentBase64 Base64-encoded Guitar Pro file bytes
	 * @param string|null $filename Original filename (e.g. "song.gp5"); used for the stored original
	 */
	#[McpTool(name: 'import_gp_file', description: 'Import a Guitar Pro file (base64) → stored original + converted alphaTex tab.')]
	public function importGpFile(int $lectureId, string $name, string $contentBase64, ?string $filename = null): McpTabResultDto
	{
		$user = $this->userContext->getUser();
		$lecture = $this->requireLecture($lectureId);

		$bytes = base64_decode($contentBase64, true);
		if ($bytes === false || $bytes === '') {
			throw new RuntimeException('contentBase64 is not valid, non-empty base64.');
		}

		try {
			$tab = $this->tabProvider->importGpFile($user, $lecture, $name, $filename ?? 'import.gp', $bytes);
		} catch (TabValidationException $e) {
			return McpTabResultDto::fromValidationException($e);
		}

		return McpTabResultDto::success(McpTabDto::fromEntity($tab));
	}

	private function requireLecture(int $lectureId): Lecture
	{
		$lecture = $this->lectureProvider->getLecture($lectureId);
		if ($lecture === null || !$this->workspaceProvider->isMember($this->userContext->getUser(), $lecture->course->workspace)) {
			throw new RuntimeException(sprintf('Lecture %d not found.', $lectureId));
		}
		return $lecture;
	}

	private function requireTab(int $tabId): Tab
	{
		$tab = $this->tabProvider->getTab($tabId);
		if ($tab === null || !$this->workspaceProvider->isMember($this->userContext->getUser(), $tab->lecture->course->workspace)) {
			throw new RuntimeException(sprintf('Tab %d not found.', $tabId));
		}
		return $tab;
	}
}
