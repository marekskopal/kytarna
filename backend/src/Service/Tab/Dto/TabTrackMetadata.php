<?php

declare(strict_types=1);

namespace Kytarna\Service\Tab\Dto;

final readonly class TabTrackMetadata
{
	/** @param list<string> $tuning */
	public function __construct(public string $name, public ?int $stringCount, public array $tuning,)
	{
	}

	/** @param array<mixed, mixed> $data */
	public static function fromArray(array $data): self
	{
		$tuning = [];
		$rawTuning = $data['tuning'] ?? null;
		if (is_array($rawTuning)) {
			foreach ($rawTuning as $note) {
				if (is_string($note)) {
					$tuning[] = $note;
				}
			}
		}

		$name = $data['name'] ?? null;
		$stringCount = $data['stringCount'] ?? null;

		return new self(
			name: is_string($name) ? $name : '',
			stringCount: is_int($stringCount) ? $stringCount : null,
			tuning: $tuning,
		);
	}
}
