<?php

declare(strict_types=1);

namespace Kytario\Mcp\Dto;

final readonly class McpTaskFileContentDto
{
	public function __construct(
		public int $id,
		public int $taskId,
		public string $filename,
		public string $mimeType,
		public int $size,
		public string $contentBase64,
	) {
	}
}
