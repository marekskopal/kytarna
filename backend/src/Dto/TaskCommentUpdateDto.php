<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use RuntimeException;

/** @implements ArrayFactoryInterface<array<string, mixed>> */
final readonly class TaskCommentUpdateDto implements ArrayFactoryInterface
{
	public function __construct(public string $body)
	{
	}

	public static function fromArray(array $data): static
	{
		$body = $data['body'] ?? null;
		if (!is_string($body)) {
			throw new RuntimeException('Comment body is required.');
		}

		return new self(body: $body);
	}
}
