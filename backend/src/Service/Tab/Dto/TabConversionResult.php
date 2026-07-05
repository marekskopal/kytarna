<?php

declare(strict_types=1);

namespace Kytario\Service\Tab\Dto;

final readonly class TabConversionResult
{
	public function __construct(public string $alphaTex, public TabMetadata $metadata,)
	{
	}
}
