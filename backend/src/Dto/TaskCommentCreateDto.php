<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use RuntimeException;

/** @implements ArrayFactoryInterface<array<string, mixed>> */
final readonly class TaskCommentCreateDto implements ArrayFactoryInterface
{
	public function __construct(public string $body, public ?int $parentCommentId = null)
	{
	}

	public static function fromArray(array $data): static
	{
		$body = $data['body'] ?? null;
		if (!is_string($body)) {
			throw new RuntimeException('Comment body is required.');
		}
		$parentCommentId = isset($data['parentCommentId']) && is_int($data['parentCommentId']) ? $data['parentCommentId'] : null;

		return new self(body: $body, parentCommentId: $parentCommentId);
	}
}
