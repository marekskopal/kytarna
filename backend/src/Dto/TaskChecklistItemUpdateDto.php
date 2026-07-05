<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use DateTimeImmutable;

/**
 * Partial update. Each `*Provided` flag records whether the caller sent the corresponding
 * key, so omitting a field leaves it unchanged while explicitly sending null clears it.
 *
 * @implements ArrayFactoryInterface<array{
 *     text?: string,
 *     dueDate?: ?string,
 *     assigneeId?: ?int,
 *     checked?: bool,
 * }>
 */
final readonly class TaskChecklistItemUpdateDto implements ArrayFactoryInterface
{
	public function __construct(
		public ?string $text,
		public bool $dueDateProvided,
		public ?DateTimeImmutable $dueDate,
		public bool $assigneeProvided,
		public ?int $assigneeId,
		public bool $checkedProvided,
		public bool $checked,
	) {
	}

	public static function fromArray(array $data): static
	{
		$dueDateProvided = array_key_exists('dueDate', $data);
		$dueDate = $dueDateProvided && $data['dueDate'] !== null && $data['dueDate'] !== ''
			? new DateTimeImmutable($data['dueDate'])
			: null;

		$assigneeProvided = array_key_exists('assigneeId', $data);
		$checkedProvided = array_key_exists('checked', $data);

		return new self(
			text: $data['text'] ?? null,
			dueDateProvided: $dueDateProvided,
			dueDate: $dueDate,
			assigneeProvided: $assigneeProvided,
			assigneeId: $assigneeProvided ? $data['assigneeId'] : null,
			checkedProvided: $checkedProvided,
			checked: $checkedProvided ? $data['checked'] : false,
		);
	}
}
