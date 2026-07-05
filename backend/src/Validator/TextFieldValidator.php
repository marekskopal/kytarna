<?php

declare(strict_types=1);

namespace Kytario\Validator;

use RuntimeException;

/**
 * Length/emptiness guard for user-supplied names and descriptions. Providers call it
 * so both the HTTP API and the MCP tools are covered. Exception code 422 maps to the
 * HTTP status via ErrorResponse::fromException.
 */
final readonly class TextFieldValidator
{
	public const int MaxNameLength = 255;
	public const int MaxDescriptionLength = 50000;

	public static function validateName(string $name, string $label): string
	{
		$trimmed = trim($name);
		if ($trimmed === '') {
			throw new RuntimeException($label . ' name is required.', 422);
		}

		if (mb_strlen($trimmed) > self::MaxNameLength) {
			throw new RuntimeException(
				sprintf('%s name is too long (max %d characters).', $label, self::MaxNameLength),
				422,
			);
		}

		return $trimmed;
	}

	public static function validateDescription(?string $description): ?string
	{
		if ($description !== null && mb_strlen($description) > self::MaxDescriptionLength) {
			throw new RuntimeException(
				sprintf('Description is too long (max %d characters).', self::MaxDescriptionLength),
				422,
			);
		}

		return $description;
	}
}
