<?php

declare(strict_types=1);

namespace Kytario\Service\Tab\Dto;

final readonly class TabValidationResult
{
	/** @param list<TabValidationError> $errors */
	public function __construct(public bool $valid, public array $errors, public ?TabMetadata $metadata,)
	{
	}
}
