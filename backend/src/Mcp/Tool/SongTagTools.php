<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Tool;

use Kytarna\Mcp\Dto\McpSongDto;
use Kytarna\Mcp\McpUserContextInterface;
use Kytarna\Model\Entity\Enum\EventTypeEnum;
use Kytarna\Model\Entity\Song;
use Kytarna\Service\Provider\EventProviderInterface;
use Kytarna\Service\Provider\SongProviderInterface;
use Kytarna\Service\Provider\SongTagProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Mcp\Capability\Attribute\McpTool;
use RuntimeException;

final readonly class SongTagTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private SongTagProviderInterface $songTagProvider,
		private SongProviderInterface $songProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private EventProviderInterface $eventProvider,
	) {
	}

	/**
	 * Replace the full set of tags applied to a song with the given IDs. Pass [] to clear.
	 *
	 * @param int $songId Song ID
	 * @param list<int> $tagIds Tag IDs (must all belong to the same workspace as the song)
	 */
	#[McpTool(name: 'set_song_tags', description: 'Replace the set of tags applied to a song')]
	public function setSongTags(int $songId, array $tagIds): McpSongDto
	{
		$song = $this->requireSong($songId);

		$tagChanges = $this->songTagProvider->setTagsForSong($song->workspace, $song, $tagIds);

		if ($tagChanges['added'] !== [] || $tagChanges['removed'] !== []) {
			$this->eventProvider->recordWorkspaceEvent(
				$this->userContext->getUser(),
				$song->workspace,
				EventTypeEnum::SongTagsUpdated,
				['songId' => $song->id, 'added' => $tagChanges['added'], 'removed' => $tagChanges['removed']],
			);
		}

		return McpSongDto::fromEntity($song);
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
