<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use DateTimeImmutable;
use Kytarna\Model\Entity\Enum\LectureLinkKindEnum;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\SongLink;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Repository\SongLinkRepository;
use RuntimeException;

final readonly class SongLinkProvider implements SongLinkProviderInterface
{
	private const int MaxUrlLength = 2048;

	public function __construct(private SongLinkRepository $songLinkRepository)
	{
	}

	/** @return list<SongLink> */
	public function getLinksBySong(Song $song): array
	{
		$result = [];
		foreach ($this->songLinkRepository->findBySong($song->id) as $link) {
			$result[] = $link;
		}
		return $result;
	}

	public function getLink(int $linkId): ?SongLink
	{
		return $this->songLinkRepository->findById($linkId);
	}

	public function addLink(
		User $author,
		Song $song,
		string $url,
		?string $label = null,
		?string $kind = null,
		?int $timestampSeconds = null,
	): SongLink {
		$url = trim($url);
		if ($url === '') {
			throw new RuntimeException('URL must not be empty.');
		}
		if (strlen($url) > self::MaxUrlLength) {
			throw new RuntimeException(sprintf('URL exceeds the %d character limit.', self::MaxUrlLength));
		}
		if (preg_match('~^https?://~i', $url) !== 1) {
			throw new RuntimeException('URL must start with http:// or https://.');
		}

		$label = $label !== null ? trim($label) : null;
		if ($label === '') {
			$label = null;
		}

		$now = new DateTimeImmutable();
		$link = new SongLink(
			song: $song,
			url: $url,
			kind: $this->resolveKind($kind, $url),
			label: $label,
			timestampSeconds: $timestampSeconds !== null && $timestampSeconds >= 0 ? $timestampSeconds : null,
		);
		$link->createdAt = $now;
		$link->updatedAt = $now;

		$this->songLinkRepository->persist($link);

		return $link;
	}

	public function deleteLink(User $author, SongLink $link): void
	{
		$this->songLinkRepository->delete($link);
	}

	private function resolveKind(?string $kind, string $url): LectureLinkKindEnum
	{
		if ($kind !== null && $kind !== '') {
			return LectureLinkKindEnum::tryFrom($kind)
				?? throw new RuntimeException('Invalid link kind; expected "youtube" or "other".');
		}

		return preg_match('~(youtube\.com|youtu\.be)~i', $url) === 1
			? LectureLinkKindEnum::Youtube
			: LectureLinkKindEnum::Other;
	}
}
