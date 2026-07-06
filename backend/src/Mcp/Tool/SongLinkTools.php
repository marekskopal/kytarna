<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Tool;

use Kytarna\Mcp\Dto\McpSongLinkDto;
use Kytarna\Mcp\Dto\McpSongLinkListDto;
use Kytarna\Mcp\McpUserContextInterface;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\SongLink;
use Kytarna\Service\Provider\SongLinkProviderInterface;
use Kytarna\Service\Provider\SongProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Mcp\Capability\Attribute\McpTool;
use RuntimeException;

final readonly class SongLinkTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private SongProviderInterface $songProvider,
		private SongLinkProviderInterface $songLinkProvider,
		private WorkspaceProviderInterface $workspaceProvider,
	) {
	}

	/**
	 * Add a reference link to a song (e.g. a YouTube lesson or a tab site). The kind is auto-detected
	 * from the URL when omitted (youtube vs other).
	 *
	 * @param int $songId Song ID
	 * @param string $url The link URL (must start with http:// or https://)
	 * @param string|null $label Optional human-readable label
	 * @param string|null $kind Optional kind: "youtube" or "other"; auto-detected when omitted
	 * @param int|null $timestampSeconds Optional start timestamp in seconds (for video links)
	 */
	#[McpTool(name: 'add_song_link', description: 'Add a reference link (YouTube/other) to a song.')]
	public function addSongLink(
		int $songId,
		string $url,
		?string $label = null,
		?string $kind = null,
		?int $timestampSeconds = null,
	): McpSongLinkDto {
		$user = $this->userContext->getUser();
		$song = $this->requireSong($songId);

		$link = $this->songLinkProvider->addLink($user, $song, $url, $label, $kind, $timestampSeconds);

		return McpSongLinkDto::fromEntity($link);
	}

	/**
	 * List the reference links attached to a song.
	 *
	 * @param int $songId Song ID
	 */
	#[McpTool(name: 'list_song_links', description: 'List the reference links attached to a song.')]
	public function listSongLinks(int $songId): McpSongLinkListDto
	{
		$song = $this->requireSong($songId);
		$links = array_map(
			static fn (SongLink $link): McpSongLinkDto => McpSongLinkDto::fromEntity($link),
			$this->songLinkProvider->getLinksBySong($song),
		);

		return new McpSongLinkListDto($links);
	}

	/**
	 * Delete a reference link from a song.
	 *
	 * @param int $linkId Link ID
	 */
	#[McpTool(name: 'delete_song_link', description: 'Delete a reference link from a song.')]
	public function deleteSongLink(int $linkId): string
	{
		$user = $this->userContext->getUser();
		$link = $this->songLinkProvider->getLink($linkId);
		if ($link === null || !$this->workspaceProvider->isMember($user, $link->song->workspace)) {
			throw new RuntimeException(sprintf('Link %d not found.', $linkId));
		}

		$this->songLinkProvider->deleteLink($user, $link);

		return 'Link deleted.';
	}

	private function requireSong(int $songId): Song
	{
		$song = $this->songProvider->getSong($songId);
		if ($song === null || !$this->workspaceProvider->isMember($this->userContext->getUser(), $song->workspace)) {
			throw new RuntimeException(sprintf('Song %d not found.', $songId));
		}
		return $song;
	}
}
