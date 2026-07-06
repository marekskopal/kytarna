<?php

declare(strict_types=1);

namespace Kytarna\Service\Tab\Dto;

final readonly class TabValidationError
{
	public function __construct(public string $message, public ?int $line = null, public ?int $col = null, public ?int $offset = null,)
	{
	}

	/** @param array<mixed, mixed> $data */
	public static function fromArray(array $data): self
	{
		$message = $data['message'] ?? null;
		$line = $data['line'] ?? null;
		$col = $data['col'] ?? null;
		$offset = $data['offset'] ?? null;

		return new self(
			message: is_string($message) ? $message : 'Invalid alphaTex.',
			line: is_int($line) ? $line : null,
			col: is_int($col) ? $col : null,
			offset: is_int($offset) ? $offset : null,
		);
	}

	/** @return array{message: string, line: ?int, col: ?int, offset: ?int} */
	public function toArray(): array
	{
		return ['message' => $this->message, 'line' => $this->line, 'col' => $this->col, 'offset' => $this->offset];
	}
}
