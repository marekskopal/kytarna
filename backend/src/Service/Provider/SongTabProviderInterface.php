<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\SongTab;
use Kytarna\Model\Entity\User;

interface SongTabProviderInterface
{
	/** @return list<SongTab> */
	public function getTabsBySong(Song $song): array;

	public function getTab(int $tabId): ?SongTab;

	/**
	 * Validates the alphaTex via the tab-service before persisting. Throws TabValidationException
	 * (carrying the errors) on invalid alphaTex and TabServiceException when the service is unreachable.
	 */
	public function createTab(User $author, Song $song, string $name, string $alphaTex): SongTab;

	/** Validates the alphaTex via the tab-service before persisting (see createTab for thrown errors). */
	public function updateTab(User $author, SongTab $tab, string $name, string $alphaTex): SongTab;

	public function deleteTab(User $author, SongTab $tab): void;

	/**
	 * Import a Guitar Pro file: store the original bytes in S3, convert to alphaTex via the tab-service,
	 * and persist a SongTab with sourceType=ImportedGp. Throws TabValidationException when the bytes cannot be
	 * parsed and TabServiceException when the service is unreachable (the stored original is cleaned up).
	 */
	public function importGpFile(User $author, Song $song, string $name, string $filename, string $bytes): SongTab;
}
