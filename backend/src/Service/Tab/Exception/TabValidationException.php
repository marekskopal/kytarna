<?php

declare(strict_types=1);

namespace Kytario\Service\Tab\Exception;

use Kytario\Service\Tab\Dto\TabValidationError;
use RuntimeException;

/**
 * Thrown when alphaTex fails validation (or a .gp file cannot be converted). Carries the
 * structured errors from the tab-service so callers can surface them (REST 422 / MCP result DTO).
 */
final class TabValidationException extends RuntimeException
{
	/** @param list<TabValidationError> $errors */
	public function __construct(public readonly array $errors, string $message = 'alphaTex validation failed.')
	{
		parent::__construct($message);
	}

	/** @return list<TabValidationError> */
	public function getErrors(): array
	{
		return $this->errors;
	}
}
