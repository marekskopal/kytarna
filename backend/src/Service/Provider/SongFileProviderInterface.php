<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\SongFile;
use Kytarna\Model\Entity\User;

interface SongFileProviderInterface
{
	public function getMaxFileSizeBytes(): int;

	/** @return list<SongFile> */
	public function findBySong(Song $song): array;

	public function getFile(int $fileId): ?SongFile;

	public function uploadFile(User $author, Song $song, string $filename, string $mimeType, string $body,): SongFile;

	public function readContent(SongFile $file): string;

	public function deleteFile(User $author, SongFile $file): void;

	public function deleteAllForSong(User $author, Song $song): void;
}
