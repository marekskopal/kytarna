<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Tool;

use Kytarna\Mcp\Dto\McpSongFileContentDto;
use Kytarna\Mcp\Dto\McpSongFileDto;
use Kytarna\Mcp\Dto\McpSongFileListDto;
use Kytarna\Mcp\McpUserContextInterface;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\SongFile;
use Kytarna\Service\Provider\SongFileProviderInterface;
use Kytarna\Service\Provider\SongProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Mcp\Capability\Attribute\McpTool;
use RuntimeException;

final readonly class SongFileTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private SongProviderInterface $songProvider,
		private SongFileProviderInterface $songFileProvider,
		private WorkspaceProviderInterface $workspaceProvider,
	) {
	}

	/**
	 * List files attached to a song.
	 *
	 * @param int $songId Song ID
	 */
	#[McpTool(name: 'list_song_files', description: 'List files attached to a song.')]
	public function listSongFiles(int $songId): McpSongFileListDto
	{
		$song = $this->requireSong($songId);
		$files = array_map(
			static fn (SongFile $file): McpSongFileDto => McpSongFileDto::fromEntity($file),
			$this->songFileProvider->findBySong($song),
		);
		return new McpSongFileListDto($files);
	}

	/**
	 * Attach a file to a song. The body must be base64-encoded.
	 * Decoded size must not exceed the server's max file size.
	 *
	 * @param int $songId Song ID
	 * @param string $filename Original filename (e.g. "design.png")
	 * @param string $mimeType MIME type (e.g. "image/png"). Use "application/octet-stream" when unknown.
	 * @param string $contentBase64 Base64-encoded file contents
	 */
	#[McpTool(name: 'attach_song_file', description: 'Attach a base64-encoded file to a song.')]
	public function attachSongFile(int $songId, string $filename, string $mimeType, string $contentBase64,): McpSongFileDto
	{
		$user = $this->userContext->getUser();
		$song = $this->requireSong($songId);

		$body = base64_decode($contentBase64, true);
		if ($body === false) {
			throw new RuntimeException('contentBase64 is not valid base64.');
		}

		$file = $this->songFileProvider->uploadFile($user, $song, $filename, $mimeType, $body);
		return McpSongFileDto::fromEntity($file);
	}

	/**
	 * Fetch a song file. Returns metadata plus base64-encoded contents.
	 * Use list_song_files first to discover the fileId.
	 *
	 * @param int $songId Song ID
	 * @param int $fileId File ID
	 */
	#[McpTool(name: 'get_song_file', description: 'Fetch a song file as base64.')]
	public function getSongFile(int $songId, int $fileId): McpSongFileContentDto
	{
		$song = $this->requireSong($songId);
		$file = $this->requireFile($song, $fileId);

		$bytes = $this->songFileProvider->readContent($file);

		return new McpSongFileContentDto(
			id: $file->id,
			songId: $file->song->id,
			filename: $file->filename,
			mimeType: $file->mimeType,
			size: $file->size,
			contentBase64: base64_encode($bytes),
		);
	}

	/**
	 * Delete a file from a song.
	 *
	 * @param int $songId Song ID
	 * @param int $fileId File ID
	 */
	#[McpTool(name: 'delete_song_file', description: 'Delete a file from a song (irreversible).')]
	public function deleteSongFile(int $songId, int $fileId): string
	{
		$user = $this->userContext->getUser();
		$song = $this->requireSong($songId);
		$file = $this->requireFile($song, $fileId);

		$this->songFileProvider->deleteFile($user, $file);
		return 'File deleted.';
	}

	private function requireSong(int $songId): Song
	{
		$song = $this->songProvider->getSong($songId);
		if ($song === null || !$this->workspaceProvider->isMember($this->userContext->getUser(), $song->workspace)) {
			throw new RuntimeException(sprintf('Song %d not found.', $songId));
		}
		return $song;
	}

	private function requireFile(Song $song, int $fileId): SongFile
	{
		$file = $this->songFileProvider->getFile($fileId);
		if ($file === null || $file->song->id !== $song->id) {
			throw new RuntimeException(sprintf('File %d not found on song %d.', $fileId, $song->id));
		}
		return $file;
	}
}
