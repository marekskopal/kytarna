<?php

declare(strict_types=1);

namespace Kytarna\Dto;

use function is_string;

/** @implements ArrayFactoryInterface<array<string, mixed>> */
final readonly class WorkspaceUpdateDto implements ArrayFactoryInterface
{
	public function __construct(public ?string $name, public ?bool $isPublic = null, public ?string $description = null,)
	{
	}

	public static function fromArray(array $data): static
	{
		$name = $data['name'] ?? null;
		$description = $data['description'] ?? null;

		return new self(
			name: is_string($name) ? $name : null,
			isPublic: isset($data['isPublic']) ? (bool) $data['isPublic'] : null,
			description: is_string($description) ? $description : null,
		);
	}
}
