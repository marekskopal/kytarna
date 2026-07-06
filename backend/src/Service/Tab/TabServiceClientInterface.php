<?php

declare(strict_types=1);

namespace Kytarna\Service\Tab;

use Kytarna\Service\Tab\Dto\TabConversionResult;
use Kytarna\Service\Tab\Dto\TabValidationResult;

interface TabServiceClientInterface
{
	/**
	 * Validate alphaTex. Always returns a result; a syntactically invalid tab is reported
	 * via TabValidationResult::$valid = false with populated errors (not thrown).
	 */
	public function validate(string $alphaTex): TabValidationResult;

	/**
	 * Convert raw Guitar Pro file bytes to alphaTex plus extracted metadata.
	 */
	public function convert(string $bytes): TabConversionResult;
}
