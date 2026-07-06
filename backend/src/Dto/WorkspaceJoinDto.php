<?php

declare(strict_types=1);

namespace Kytarna\Dto;

use function is_string;

/** @implements ArrayFactoryInterface<array<string, mixed>> */
final readonly class WorkspaceJoinDto implements ArrayFactoryInterface
{
	public function __construct(public string $code)
	{
	}

	public static function fromArray(array $data): static
	{
		$code = $data['code'] ?? '';
		return new self(code: is_string($code) ? $code : '');
	}
}
