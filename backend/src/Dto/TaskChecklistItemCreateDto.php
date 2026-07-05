<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use DateTimeImmutable;

/**
 * @implements ArrayFactoryInterface<array{
 *     text?: string,
 *     dueDate?: ?string,
 *     assigneeId?: ?int,
 * }>
 */
final readonly class TaskChecklistItemCreateDto implements ArrayFactoryInterface
{
	public function __construct(public string $text, public ?DateTimeImmutable $dueDate, public ?int $assigneeId,)
	{
	}

	public static function fromArray(array $data): static
	{
		$dueDate = isset($data['dueDate']) && $data['dueDate'] !== ''
			? new DateTimeImmutable($data['dueDate'])
			: null;

		return new self(text: $data['text'] ?? '', dueDate: $dueDate, assigneeId: $data['assigneeId'] ?? null);
	}
}
