<?php

declare(strict_types=1);

namespace Ukolio\Dto;

final readonly class GoogleClientIdDto
{
	public function __construct(public string $googleClientId)
	{
	}
}
