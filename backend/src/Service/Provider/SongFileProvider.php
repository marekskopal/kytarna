<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use DateTimeImmutable;
use Kytarna\Model\Entity\Enum\EventTypeEnum;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\SongFile;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Repository\SongFileRepository;
use Kytarna\Service\Actor\ActorContextInterface;
use Kytarna\Service\Storage\FileStorageInterface;
use Kytarna\Service\Storage\S3Config;
use RuntimeException;

final readonly class SongFileProvider implements SongFileProviderInterface
{
	public function __construct(
		private SongFileRepository $songFileRepository,
		private FileStorageInterface $fileStorage,
		private S3Config $s3Config,
		private EventProviderInterface $eventProvider,
		private ActorContextInterface $actorContext,
	) {
	}

	public function getMaxFileSizeBytes(): int
	{
		return $this->s3Config->maxFileSizeBytes;
	}

	/** @return list<SongFile> */
	public function findBySong(Song $song): array
	{
		$result = [];
		foreach ($this->songFileRepository->findBySong($song->id) as $file) {
			$result[] = $file;
		}
		return $result;
	}

	public function getFile(int $fileId): ?SongFile
	{
		return $this->songFileRepository->findOneById($fileId);
	}

	public function uploadFile(User $author, Song $song, string $filename, string $mimeType, string $body): SongFile
	{
		$size = strlen($body);
		if ($size === 0) {
			throw new RuntimeException('File body is empty.');
		}
		$max = $this->s3Config->maxFileSizeBytes;
		if ($size > $max) {
			throw new RuntimeException(sprintf(
				'File is %d bytes, exceeds the %d-byte limit.',
				$size,
				$max,
			));
		}

		$cleanFilename = $this->sanitizeFilename($filename);
		$cleanMimeType = $this->sanitizeMimeType($mimeType);
		$storageKey = $this->buildStorageKey($song, $cleanFilename);

		$now = new DateTimeImmutable();
		$file = new SongFile(
			song: $song,
			filename: $cleanFilename,
			mimeType: $cleanMimeType,
			size: $size,
			storageKey: $storageKey,
			uploadedBy: $author,
			uploadedByAgent: $this->actorContext->isAgent(),
		);
		$file->createdAt = $now;
		$file->updatedAt = $now;

		$this->songFileRepository->persist($file);

		try {
			$this->fileStorage->put($storageKey, $body, $cleanMimeType);
		} catch (\Throwable $e) {
			$this->songFileRepository->delete($file);

			throw new RuntimeException('Failed to store file: ' . $e->getMessage(), 0, $e);
		}

		$this->eventProvider->recordWorkspaceEvent(
			$author,
			$song->workspace,
			EventTypeEnum::SongFileAdded,
			['songId' => $song->id, 'fileId' => $file->id, 'filename' => $cleanFilename, 'size' => $size],
		);

		return $file;
	}

	public function readContent(SongFile $file): string
	{
		return $this->fileStorage->get($file->storageKey);
	}

	public function deleteFile(User $author, SongFile $file): void
	{
		$this->fileStorage->delete($file->storageKey);
		$this->songFileRepository->delete($file);

		$this->eventProvider->recordWorkspaceEvent(
			$author,
			$file->song->workspace,
			EventTypeEnum::SongFileDeleted,
			['songId' => $file->song->id, 'fileId' => $file->id, 'filename' => $file->filename, 'size' => $file->size],
		);
	}

	public function deleteAllForSong(User $author, Song $song): void
	{
		foreach ($this->songFileRepository->findBySong($song->id) as $file) {
			$this->fileStorage->delete($file->storageKey);
			$this->songFileRepository->delete($file);
		}
	}

	private function buildStorageKey(Song $song, string $filename): string
	{
		$uuid = bin2hex(random_bytes(16));
		return sprintf('workspaces/%d/songs/%d/%s-%s', $song->workspace->id, $song->id, $uuid, $filename);
	}

	private function sanitizeFilename(string $filename): string
	{
		$basename = basename(str_replace(['\\', '/'], '_', $filename));
		$basename = preg_replace('/[^A-Za-z0-9._\-]+/', '_', $basename) ?? '';
		$basename = trim($basename, '._-');
		if ($basename === '') {
			$basename = 'file';
		}
		if (strlen($basename) > 200) {
			$basename = substr($basename, 0, 200);
		}
		return $basename;
	}

	private function sanitizeMimeType(string $mimeType): string
	{
		$trimmed = trim($mimeType);
		if ($trimmed === '') {
			return 'application/octet-stream';
		}
		if (preg_match('~^[a-zA-Z0-9!#$&\^_.+-]+/[a-zA-Z0-9!#$&\^_.+-]+$~', $trimmed) !== 1) {
			return 'application/octet-stream';
		}
		if (strlen($trimmed) > 191) {
			return 'application/octet-stream';
		}
		return $trimmed;
	}
}
