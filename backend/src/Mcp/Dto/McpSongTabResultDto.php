<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

use Kytarna\Service\Tab\Dto\TabValidationError;
use Kytarna\Service\Tab\Exception\TabValidationException;

/**
 * Result of create_song_tab / update_song_tab / import_song_gp_file. On success `valid` is true and `tab`
 * is set; on invalid alphaTex `valid` is false and `errors` carries the tab-service validation errors so the
 * agent can fix and retry (instead of receiving an opaque tool error).
 */
final readonly class McpSongTabResultDto
{
	/** @param list<array{message: string, line: ?int, col: ?int, offset: ?int}> $errors */
	public function __construct(public bool $valid, public ?McpSongTabDto $tab, public array $errors = [],)
	{
	}

	public static function success(McpSongTabDto $tab): self
	{
		return new self(true, $tab);
	}

	public static function fromValidationException(TabValidationException $e): self
	{
		return new self(
			false,
			null,
			array_map(static fn (TabValidationError $error): array => $error->toArray(), $e->getErrors()),
		);
	}
}
