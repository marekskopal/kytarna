<?php

declare(strict_types=1);

namespace Kytario\Dto;

/**
 * @implements ArrayFactoryInterface<array{
 *     url: string,
 *     label?: ?string,
 *     kind?: ?string,
 *     timestampSeconds?: ?int,
 * }>
 */
final readonly class LectureLinkCreateDto implements ArrayFactoryInterface
{
	public function __construct(public string $url, public ?string $label, public ?string $kind, public ?int $timestampSeconds,)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(
			url: $data['url'],
			label: $data['label'] ?? null,
			kind: $data['kind'] ?? null,
			timestampSeconds: $data['timestampSeconds'] ?? null,
		);
	}
}
