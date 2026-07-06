<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\LectureFile;
use Kytarna\Model\Entity\User;

interface LectureFileProviderInterface
{
	public function getMaxFileSizeBytes(): int;

	/** @return list<LectureFile> */
	public function findByLecture(Lecture $lecture): array;

	public function getFile(int $fileId): ?LectureFile;

	public function uploadFile(User $author, Lecture $lecture, string $filename, string $mimeType, string $body,): LectureFile;

	public function readContent(LectureFile $file): string;

	public function deleteFile(User $author, LectureFile $file): void;

	public function deleteAllForLecture(User $author, Lecture $lecture): void;
}
