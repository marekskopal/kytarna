<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use DateTimeImmutable;
use Kytario\Model\Entity\Enum\EventTypeEnum;
use Kytario\Model\Entity\Lecture;
use Kytario\Model\Entity\LectureFile;
use Kytario\Model\Entity\User;
use Kytario\Model\Repository\LectureFileRepository;
use Kytario\Service\Actor\ActorContextInterface;
use Kytario\Service\Storage\FileStorageInterface;
use Kytario\Service\Storage\S3Config;
use RuntimeException;

final readonly class LectureFileProvider implements LectureFileProviderInterface
{
	public function __construct(
		private LectureFileRepository $lectureFileRepository,
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

	/** @return list<LectureFile> */
	public function findByLecture(Lecture $lecture): array
	{
		$result = [];
		foreach ($this->lectureFileRepository->findByLecture($lecture->id) as $file) {
			$result[] = $file;
		}
		return $result;
	}

	public function getFile(int $fileId): ?LectureFile
	{
		return $this->lectureFileRepository->findOneById($fileId);
	}

	public function uploadFile(User $author, Lecture $lecture, string $filename, string $mimeType, string $body): LectureFile
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
		$storageKey = $this->buildStorageKey($lecture, $cleanFilename);

		$now = new DateTimeImmutable();
		$file = new LectureFile(
			lecture: $lecture,
			filename: $cleanFilename,
			mimeType: $cleanMimeType,
			size: $size,
			storageKey: $storageKey,
			uploadedBy: $author,
			uploadedByAgent: $this->actorContext->isAgent(),
		);
		$file->createdAt = $now;
		$file->updatedAt = $now;

		$this->lectureFileRepository->persist($file);

		try {
			$this->fileStorage->put($storageKey, $body, $cleanMimeType);
		} catch (\Throwable $e) {
			$this->lectureFileRepository->delete($file);

			throw new RuntimeException('Failed to store file: ' . $e->getMessage(), 0, $e);
		}

		$this->eventProvider->recordEvent(
			$author,
			$lecture->course,
			EventTypeEnum::LectureFileAdded,
			['fileId' => $file->id, 'filename' => $cleanFilename, 'size' => $size],
			$lecture->id,
		);

		return $file;
	}

	public function readContent(LectureFile $file): string
	{
		return $this->fileStorage->get($file->storageKey);
	}

	public function deleteFile(User $author, LectureFile $file): void
	{
		$this->fileStorage->delete($file->storageKey);
		$this->lectureFileRepository->delete($file);

		$this->eventProvider->recordEvent(
			$author,
			$file->lecture->course,
			EventTypeEnum::LectureFileDeleted,
			['fileId' => $file->id, 'filename' => $file->filename, 'size' => $file->size],
			$file->lecture->id,
		);
	}

	public function deleteAllForLecture(User $author, Lecture $lecture): void
	{
		foreach ($this->lectureFileRepository->findByLecture($lecture->id) as $file) {
			$this->fileStorage->delete($file->storageKey);
			$this->lectureFileRepository->delete($file);
		}
	}

	private function buildStorageKey(Lecture $lecture, string $filename): string
	{
		$uuid = bin2hex(random_bytes(16));
		return sprintf('workspaces/%d/lectures/%d/%s-%s', $lecture->course->workspace->id, $lecture->id, $uuid, $filename);
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
