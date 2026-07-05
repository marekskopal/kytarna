<?php

declare(strict_types=1);

namespace Kytario\Service\Tab\Dto;

final readonly class TabMetadata
{
	/** @param list<TabTrackMetadata> $tracks */
	public function __construct(
		public ?string $title,
		public ?string $artist,
		public ?string $album,
		public ?int $tempo,
		public ?int $trackCount,
		public array $tracks,
	) {
	}

	/** @param array<mixed, mixed> $data */
	public static function fromArray(array $data): self
	{
		return new self(
			title: self::stringOrNull($data['title'] ?? null),
			artist: self::stringOrNull($data['artist'] ?? null),
			album: self::stringOrNull($data['album'] ?? null),
			tempo: self::intOrNull($data['tempo'] ?? null),
			trackCount: self::intOrNull($data['trackCount'] ?? null),
			tracks: self::parseTracks($data['tracks'] ?? null),
		);
	}

	/** Best-effort human-readable tuning of the first track, e.g. "E A D G B E". */
	public function primaryTuning(): ?string
	{
		$first = $this->tracks[0] ?? null;
		if ($first === null || $first->tuning === []) {
			return null;
		}
		return implode(' ', $first->tuning);
	}

	/** @return list<TabTrackMetadata> */
	private static function parseTracks(mixed $raw): array
	{
		if (!is_array($raw)) {
			return [];
		}
		$tracks = [];
		foreach ($raw as $track) {
			if (is_array($track)) {
				$tracks[] = TabTrackMetadata::fromArray($track);
			}
		}
		return $tracks;
	}

	private static function stringOrNull(mixed $value): ?string
	{
		return is_string($value) ? $value : null;
	}

	private static function intOrNull(mixed $value): ?int
	{
		return is_int($value) ? $value : null;
	}
}
