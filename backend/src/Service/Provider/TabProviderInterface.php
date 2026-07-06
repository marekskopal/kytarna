<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\Tab;
use Kytarna\Model\Entity\User;

interface TabProviderInterface
{
	/** @return list<Tab> */
	public function getTabsByLecture(Lecture $lecture): array;

	public function getTab(int $tabId): ?Tab;

	/**
	 * Validates the alphaTex via the tab-service before persisting. Throws TabValidationException
	 * (carrying the errors) on invalid alphaTex and TabServiceException when the service is unreachable.
	 */
	public function createTab(User $author, Lecture $lecture, string $name, string $alphaTex): Tab;

	/** Validates the alphaTex via the tab-service before persisting (see createTab for thrown errors). */
	public function updateTab(User $author, Tab $tab, string $name, string $alphaTex): Tab;

	public function deleteTab(User $author, Tab $tab): void;

	/**
	 * Import a Guitar Pro file: store the original bytes in S3, convert to alphaTex via the tab-service,
	 * and persist a Tab with sourceType=ImportedGp. Throws TabValidationException when the bytes cannot be
	 * parsed and TabServiceException when the service is unreachable (the stored original is cleaned up).
	 */
	public function importGpFile(User $author, Lecture $lecture, string $name, string $filename, string $bytes): Tab;
}
