<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

final readonly class McpSongFileContentDto
{
	public function __construct(
		public int $id,
		public int $songId,
		public string $filename,
		public string $mimeType,
		public int $size,
		public string $contentBase64,
	) {
	}
}
