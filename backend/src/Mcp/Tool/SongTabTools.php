<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Tool;

use Kytarna\Mcp\Dto\McpSongTabDto;
use Kytarna\Mcp\Dto\McpSongTabListDto;
use Kytarna\Mcp\Dto\McpSongTabResultDto;
use Kytarna\Mcp\McpUserContextInterface;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\SongTab;
use Kytarna\Service\Provider\SongProviderInterface;
use Kytarna\Service\Provider\SongTabProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Kytarna\Service\Tab\Exception\TabValidationException;
use Mcp\Capability\Attribute\McpTool;
use RuntimeException;

final readonly class SongTabTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private SongProviderInterface $songProvider,
		private SongTabProviderInterface $songTabProvider,
		private WorkspaceProviderInterface $workspaceProvider,
	) {
	}

	/**
	 * List the tabs attached to a song (alphaTex + extracted metadata).
	 *
	 * @param int $songId Song ID
	 */
	#[McpTool(name: 'list_song_tabs', description: 'List the tabs (alphaTex notation) attached to a song.')]
	public function listSongTabs(int $songId): McpSongTabListDto
	{
		$song = $this->requireSong($songId);
		$tabs = array_map(
			static fn (SongTab $tab): McpSongTabDto => McpSongTabDto::fromEntity($tab),
			$this->songTabProvider->getTabsBySong($song),
		);
		return new McpSongTabListDto($tabs);
	}

	/**
	 * Get a single song tab, including its full alphaTex content and extracted metadata (tempo, tuning, track count).
	 *
	 * @param int $tabId Tab ID
	 */
	#[McpTool(name: 'get_song_tab', description: 'Get a song tab with its alphaTex content and metadata.')]
	public function getSongTab(int $tabId): McpSongTabDto
	{
		return McpSongTabDto::fromEntity($this->requireTab($tabId));
	}

	/**
	 * Create a song tab from alphaTex. The alphaTex is validated by the tab-service before saving; if it is
	 * invalid, the result has valid=false and lists the errors (with line/col) so you can fix and retry.
	 *
	 * @param int $songId Song ID
	 * @param string $name Tab name (e.g. "Main riff")
	 * @param string $alphaTex The tab content in alphaTex notation
	 */
	#[McpTool(
		name: 'create_song_tab',
		description: 'Create a song tab from alphaTex (validated before saving; returns errors if invalid).',
	)]
	public function createSongTab(int $songId, string $name, string $alphaTex): McpSongTabResultDto
	{
		$user = $this->userContext->getUser();
		$song = $this->requireSong($songId);

		try {
			$tab = $this->songTabProvider->createTab($user, $song, $name, $alphaTex);
		} catch (TabValidationException $e) {
			return McpSongTabResultDto::fromValidationException($e);
		}

		return McpSongTabResultDto::success(McpSongTabDto::fromEntity($tab));
	}

	/**
	 * Update a song tab's name and alphaTex content. The alphaTex is validated by the tab-service before saving;
	 * if it is invalid, the result has valid=false and lists the errors so you can fix and retry.
	 *
	 * @param int $tabId Tab ID
	 * @param string $name New tab name
	 * @param string $alphaTex New alphaTex content
	 */
	#[McpTool(name: 'update_song_tab', description: 'Update a song tab (validated before saving; returns errors if invalid).')]
	public function updateSongTab(int $tabId, string $name, string $alphaTex): McpSongTabResultDto
	{
		$user = $this->userContext->getUser();
		$tab = $this->requireTab($tabId);

		try {
			$updated = $this->songTabProvider->updateTab($user, $tab, $name, $alphaTex);
		} catch (TabValidationException $e) {
			return McpSongTabResultDto::fromValidationException($e);
		}

		return McpSongTabResultDto::success(McpSongTabDto::fromEntity($updated));
	}

	/**
	 * Delete a song tab.
	 *
	 * @param int $tabId Tab ID
	 */
	#[McpTool(name: 'delete_song_tab', description: 'Delete a song tab (irreversible).')]
	public function deleteSongTab(int $tabId): string
	{
		$user = $this->userContext->getUser();
		$tab = $this->requireTab($tabId);
		$this->songTabProvider->deleteTab($user, $tab);
		return 'Tab deleted.';
	}

	/**
	 * Import a Guitar Pro file (gp3–gp8 / MusicXML). The base64-encoded bytes are stored in S3 and converted
	 * to alphaTex by the tab-service; a new tab (sourceType=imported_gp) is created with the converted content
	 * and extracted metadata. If the file cannot be parsed, the result has valid=false and lists the errors.
	 *
	 * @param int $songId Song ID
	 * @param string $name Tab name
	 * @param string $contentBase64 Base64-encoded Guitar Pro file bytes
	 * @param string|null $filename Original filename (e.g. "song.gp5"); used for the stored original
	 */
	#[McpTool(
		name: 'import_song_gp_file',
		description: 'Import a Guitar Pro file (base64) → stored original + converted alphaTex song tab.',
	)]
	public function importSongGpFile(int $songId, string $name, string $contentBase64, ?string $filename = null): McpSongTabResultDto
	{
		$user = $this->userContext->getUser();
		$song = $this->requireSong($songId);

		$bytes = base64_decode($contentBase64, true);
		if ($bytes === false || $bytes === '') {
			throw new RuntimeException('contentBase64 is not valid, non-empty base64.');
		}

		try {
			$tab = $this->songTabProvider->importGpFile($user, $song, $name, $filename ?? 'import.gp', $bytes);
		} catch (TabValidationException $e) {
			return McpSongTabResultDto::fromValidationException($e);
		}

		return McpSongTabResultDto::success(McpSongTabDto::fromEntity($tab));
	}

	private function requireSong(int $songId): Song
	{
		$song = $this->songProvider->getSong($songId);
		if ($song === null || !$this->workspaceProvider->isMember($this->userContext->getUser(), $song->workspace)) {
			throw new RuntimeException(sprintf('Song %d not found.', $songId));
		}
		return $song;
	}

	private function requireTab(int $tabId): SongTab
	{
		$tab = $this->songTabProvider->getTab($tabId);
		if ($tab === null || !$this->workspaceProvider->isMember($this->userContext->getUser(), $tab->song->workspace)) {
			throw new RuntimeException(sprintf('Tab %d not found.', $tabId));
		}
		return $tab;
	}
}
