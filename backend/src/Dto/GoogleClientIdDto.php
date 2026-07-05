<?php

declare(strict_types=1);

namespace Kytario\Dto;

final readonly class GoogleClientIdDto
{
	public function __construct(public string $googleClientId)
	{
	}
}
