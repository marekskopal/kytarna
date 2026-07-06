<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use DateTimeImmutable;
use Kytarna\Model\Entity\Enum\TabSourceTypeEnum;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\SongFile;
use Kytarna\Model\Entity\SongTab;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Repository\SongTabRepository;
use Kytarna\Service\Tab\Dto\TabConversionResult;
use Kytarna\Service\Tab\Dto\TabMetadata;
use Kytarna\Service\Tab\Exception\TabValidationException;
use Kytarna\Service\Tab\TabServiceClientInterface;
use Kytarna\Validator\TextFieldValidator;

final readonly class SongTabProvider implements SongTabProviderInterface
{
	public function __construct(
		private SongTabRepository $songTabRepository,
		private TabServiceClientInterface $tabServiceClient,
		private SongFileProviderInterface $songFileProvider,
	) {
	}

	/** @return list<SongTab> */
	public function getTabsBySong(Song $song): array
	{
		$result = [];
		foreach ($this->songTabRepository->findBySong($song->id) as $tab) {
			$result[] = $tab;
		}
		return $result;
	}

	public function getTab(int $tabId): ?SongTab
	{
		return $this->songTabRepository->findById($tabId);
	}

	public function createTab(User $author, Song $song, string $name, string $alphaTex): SongTab
	{
		$name = TextFieldValidator::validateName($name, 'Tab');
		$metadata = $this->validateAlphaTex($alphaTex);

		$now = new DateTimeImmutable();
		$tab = new SongTab(
			song: $song,
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

		$this->songTabRepository->persist($tab);

		return $tab;
	}

	public function updateTab(User $author, SongTab $tab, string $name, string $alphaTex): SongTab
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

		$this->songTabRepository->persist($tab);

		return $tab;
	}

	public function deleteTab(User $author, SongTab $tab): void
	{
		$this->songTabRepository->delete($tab);
	}

	public function importGpFile(User $author, Song $song, string $name, string $filename, string $bytes): SongTab
	{
		$name = TextFieldValidator::validateName($name, 'Tab');

		// Store the original .gp first so imported tabs keep full fidelity next to the (possibly lossy) alphaTex.
		$file = $this->songFileProvider->uploadFile($author, $song, $filename, 'application/octet-stream', $bytes);

		$conversion = $this->convertOrCleanUp($author, $file, $bytes);

		$now = new DateTimeImmutable();
		$tab = new SongTab(
			song: $song,
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

		$this->songTabRepository->persist($tab);

		return $tab;
	}

	/** Convert the stored bytes; on any failure remove the just-stored SongFile so no orphan is left behind. */
	private function convertOrCleanUp(User $author, SongFile $file, string $bytes): TabConversionResult
	{
		try {
			return $this->tabServiceClient->convert($bytes);
		} catch (\Throwable $e) {
			$this->songFileProvider->deleteFile($author, $file);

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
