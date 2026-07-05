<?php

declare(strict_types=1);

namespace Kytario\Mcp\Tool;

use Kytario\Mcp\Dto\McpLectureFileContentDto;
use Kytario\Mcp\Dto\McpLectureFileDto;
use Kytario\Mcp\Dto\McpLectureFileListDto;
use Kytario\Mcp\McpUserContextInterface;
use Kytario\Model\Entity\Lecture;
use Kytario\Model\Entity\LectureFile;
use Kytario\Service\Provider\LectureFileProviderInterface;
use Kytario\Service\Provider\LectureProviderInterface;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use Mcp\Capability\Attribute\McpTool;
use RuntimeException;

final readonly class LectureFileTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private LectureProviderInterface $lectureProvider,
		private LectureFileProviderInterface $lectureFileProvider,
		private WorkspaceProviderInterface $workspaceProvider,
	) {
	}

	/**
	 * List files attached to a lecture.
	 *
	 * @param int $lectureId Lecture ID
	 */
	#[McpTool(name: 'list_lecture_files', description: 'List files attached to a lecture.')]
	public function listLectureFiles(int $lectureId): McpLectureFileListDto
	{
		$lecture = $this->requireLecture($lectureId);
		$files = array_map(
			static fn (LectureFile $file): McpLectureFileDto => McpLectureFileDto::fromEntity($file),
			$this->lectureFileProvider->findByLecture($lecture),
		);
		return new McpLectureFileListDto($files);
	}

	/**
	 * Attach a file to a lecture. The body must be base64-encoded.
	 * Decoded size must not exceed the server's max file size.
	 *
	 * @param int $lectureId Lecture ID
	 * @param string $filename Original filename (e.g. "design.png")
	 * @param string $mimeType MIME type (e.g. "image/png"). Use "application/octet-stream" when unknown.
	 * @param string $contentBase64 Base64-encoded file contents
	 */
	#[McpTool(name: 'attach_file', description: 'Attach a base64-encoded file to a lecture.')]
	public function attachFile(int $lectureId, string $filename, string $mimeType, string $contentBase64,): McpLectureFileDto
	{
		$user = $this->userContext->getUser();
		$lecture = $this->requireLecture($lectureId);

		$body = base64_decode($contentBase64, true);
		if ($body === false) {
			throw new RuntimeException('contentBase64 is not valid base64.');
		}

		$file = $this->lectureFileProvider->uploadFile($user, $lecture, $filename, $mimeType, $body);
		return McpLectureFileDto::fromEntity($file);
	}

	/**
	 * Fetch a lecture file. Returns metadata plus base64-encoded contents.
	 * Use list_lecture_files first to discover the fileId.
	 *
	 * @param int $lectureId Lecture ID
	 * @param int $fileId File ID
	 */
	#[McpTool(name: 'get_lecture_file', description: 'Fetch a lecture file as base64.')]
	public function getLectureFile(int $lectureId, int $fileId): McpLectureFileContentDto
	{
		$lecture = $this->requireLecture($lectureId);
		$file = $this->requireFile($lecture, $fileId);

		$bytes = $this->lectureFileProvider->readContent($file);

		return new McpLectureFileContentDto(
			id: $file->id,
			lectureId: $file->lecture->id,
			filename: $file->filename,
			mimeType: $file->mimeType,
			size: $file->size,
			contentBase64: base64_encode($bytes),
		);
	}

	/**
	 * Delete a file from a lecture.
	 *
	 * @param int $lectureId Lecture ID
	 * @param int $fileId File ID
	 */
	#[McpTool(name: 'delete_lecture_file', description: 'Delete a file from a lecture (irreversible).')]
	public function deleteLectureFile(int $lectureId, int $fileId): string
	{
		$user = $this->userContext->getUser();
		$lecture = $this->requireLecture($lectureId);
		$file = $this->requireFile($lecture, $fileId);

		$this->lectureFileProvider->deleteFile($user, $file);
		return 'File deleted.';
	}

	private function requireLecture(int $lectureId): Lecture
	{
		$lecture = $this->lectureProvider->getLecture($lectureId);
		if ($lecture === null || !$this->workspaceProvider->isMember($this->userContext->getUser(), $lecture->course->workspace)) {
			throw new RuntimeException(sprintf('Lecture %d not found.', $lectureId));
		}
		return $lecture;
	}

	private function requireFile(Lecture $lecture, int $fileId): LectureFile
	{
		$file = $this->lectureFileProvider->getFile($fileId);
		if ($file === null || $file->lecture->id !== $lecture->id) {
			throw new RuntimeException(sprintf('File %d not found on lecture %d.', $fileId, $lecture->id));
		}
		return $file;
	}
}
