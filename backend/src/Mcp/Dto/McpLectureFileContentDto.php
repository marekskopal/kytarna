<?php

declare(strict_types=1);

namespace Kytario\Mcp\Dto;

final readonly class McpLectureFileContentDto
{
	public function __construct(
		public int $id,
		public int $lectureId,
		public string $filename,
		public string $mimeType,
		public int $size,
		public string $contentBase64,
	) {
	}
}
