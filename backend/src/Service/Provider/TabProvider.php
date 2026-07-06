<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use DateTimeImmutable;
use Kytarna\Model\Entity\Enum\TabSourceTypeEnum;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\LectureFile;
use Kytarna\Model\Entity\Tab;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Repository\TabRepository;
use Kytarna\Service\Tab\Dto\TabConversionResult;
use Kytarna\Service\Tab\Dto\TabMetadata;
use Kytarna\Service\Tab\Exception\TabValidationException;
use Kytarna\Service\Tab\TabServiceClientInterface;
use Kytarna\Validator\TextFieldValidator;

final readonly class TabProvider implements TabProviderInterface
{
	public function __construct(
		private TabRepository $tabRepository,
		private TabServiceClientInterface $tabServiceClient,
		private LectureFileProviderInterface $lectureFileProvider,
	) {
	}

	/** @return list<Tab> */
	public function getTabsByLecture(Lecture $lecture): array
	{
		$result = [];
		foreach ($this->tabRepository->findByLecture($lecture->id) as $tab) {
			$result[] = $tab;
		}
		return $result;
	}

	public function getTab(int $tabId): ?Tab
	{
		return $this->tabRepository->findById($tabId);
	}

	public function createTab(User $author, Lecture $lecture, string $name, string $alphaTex): Tab
	{
		$name = TextFieldValidator::validateName($name, 'Tab');
		$metadata = $this->validateAlphaTex($alphaTex);

		$now = new DateTimeImmutable();
		$tab = new Tab(
			lecture: $lecture,
			name: $name,
			alphatexContent: $alphaTex,
			sourceType: TabSourceTypeEnum::Authored,
			originalFile: null,
			tempo: $metadata?->tempo,
			tuning: $metadata?->primaryTuning(),
			trackCount: $metadata?->trackCount,
		);
		$tab->createdAt = $now;
		$tab->updatedAt = $now;

		$this->tabRepository->persist($tab);

		return $tab;
	}

	public function updateTab(User $author, Tab $tab, string $name, string $alphaTex): Tab
	{
		$name = TextFieldValidator::validateName($name, 'Tab');
		$metadata = $this->validateAlphaTex($alphaTex);

		$tab->name = $name;
		$tab->alphatexContent = $alphaTex;
		if ($metadata !== null) {
			$tab->tempo = $metadata->tempo;
			$tab->tuning = $metadata->primaryTuning();
			$tab->trackCount = $metadata->trackCount;
		}
		$tab->updatedAt = new DateTimeImmutable();

		$this->tabRepository->persist($tab);

		return $tab;
	}

	public function deleteTab(User $author, Tab $tab): void
	{
		$this->tabRepository->delete($tab);
	}

	public function importGpFile(User $author, Lecture $lecture, string $name, string $filename, string $bytes): Tab
	{
		$name = TextFieldValidator::validateName($name, 'Tab');

		// Store the original .gp first so imported tabs keep full fidelity next to the (possibly lossy) alphaTex.
		$file = $this->lectureFileProvider->uploadFile($author, $lecture, $filename, 'application/octet-stream', $bytes);

		$conversion = $this->convertOrCleanUp($author, $file, $bytes);

		$now = new DateTimeImmutable();
		$tab = new Tab(
			lecture: $lecture,
			name: $name,
			alphatexContent: $conversion->alphaTex,
			sourceType: TabSourceTypeEnum::ImportedGp,
			originalFile: $file,
			tempo: $conversion->metadata->tempo,
			tuning: $conversion->metadata->primaryTuning(),
			trackCount: $conversion->metadata->trackCount,
		);
		$tab->createdAt = $now;
		$tab->updatedAt = $now;

		$this->tabRepository->persist($tab);

		return $tab;
	}

	/** Convert the stored bytes; on any failure remove the just-stored LectureFile so no orphan is left behind. */
	private function convertOrCleanUp(User $author, LectureFile $file, string $bytes): TabConversionResult
	{
		try {
			return $this->tabServiceClient->convert($bytes);
		} catch (\Throwable $e) {
			$this->lectureFileProvider->deleteFile($author, $file);

			throw $e;
		}
	}

	private function validateAlphaTex(string $alphaTex): ?TabMetadata
	{
		$result = $this->tabServiceClient->validate($alphaTex);
		if (!$result->valid) {
			throw new TabValidationException($result->errors);
		}
		return $result->metadata;
	}
}
