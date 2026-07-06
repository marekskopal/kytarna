<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\SongLink;
use Kytarna\Model\Entity\User;

interface SongLinkProviderInterface
{
	/** @return list<SongLink> */
	public function getLinksBySong(Song $song): array;

	public function getLink(int $linkId): ?SongLink;

	/** @param string|null $kind "youtube" | "other"; null auto-detects from the URL. */
	public function addLink(
		User $author,
		Song $song,
		string $url,
		?string $label = null,
		?string $kind = null,
		?int $timestampSeconds = null,
	): SongLink;

	public function deleteLink(User $author, SongLink $link): void;
}
