<?php

declare(strict_types=1);

namespace Kytario\Tests\Service\Provider\Fake;

use DateTimeImmutable;
use Kytario\Model\Entity\Lecture;
use Kytario\Model\Entity\LectureFile;
use Kytario\Model\Entity\User;
use Kytario\Model\Repository\LectureFileRepository;
use Kytario\Service\Provider\LectureFileProviderInterface;

/**
 * Persists LectureFile rows via the real repository (so foreign keys stay valid) but skips S3 entirely,
 * keeping the stored bytes in memory. Lets tab import tests run without a MinIO/S3 backend.
 */
final class FakeLectureFileProvider implements LectureFileProviderInterface
{
	/** @var array<int, string> */
	public array $storedBytes = [];

	public bool $failOnUpload = false;

	public function __construct(private readonly LectureFileRepository $lectureFileRepository)
	{
	}

	public function getMaxFileSizeBytes(): int
	{
		return 25 * 1024 * 1024;
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
		if ($this->failOnUpload) {
			throw new \RuntimeException('Upload failed (fake).');
		}

		$now = new DateTimeImmutable();
		$file = new LectureFile(
			lecture: $lecture,
			filename: $filename,
			mimeType: $mimeType,
			size: strlen($body),
			storageKey: 'fake/' . bin2hex(random_bytes(8)) . '/' . $filename,
			uploadedBy: $author,
			uploadedByAgent: false,
		);
		$file->createdAt = $now;
		$file->updatedAt = $now;
		$this->lectureFileRepository->persist($file);

		$this->storedBytes[$file->id] = $body;

		return $file;
	}

	public function readContent(LectureFile $file): string
	{
		return $this->storedBytes[$file->id] ?? '';
	}

	public function deleteFile(User $author, LectureFile $file): void
	{
		unset($this->storedBytes[$file->id]);
		$this->lectureFileRepository->delete($file);
	}

	public function deleteAllForLecture(User $author, Lecture $lecture): void
	{
		foreach ($this->findByLecture($lecture) as $file) {
			$this->deleteFile($author, $file);
		}
	}
}
