<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\LectureLink;
use Kytarna\Model\Entity\User;

interface LinkProviderInterface
{
	/** @return list<LectureLink> */
	public function getLinksByLecture(Lecture $lecture): array;

	public function getLink(int $linkId): ?LectureLink;

	/** @param string|null $kind "youtube" | "other"; null auto-detects from the URL. */
	public function addLink(
		User $author,
		Lecture $lecture,
		string $url,
		?string $label = null,
		?string $kind = null,
		?int $timestampSeconds = null,
	): LectureLink;

	public function deleteLink(User $author, LectureLink $link): void;
}
