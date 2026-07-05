<?php

declare(strict_types=1);

namespace Kytario\Tests\Service\Provider;

use Kytario\Model\Entity\Enum\TabSourceTypeEnum;
use Kytario\Model\Entity\Lecture;
use Kytario\Model\Entity\User;
use Kytario\Model\Repository\LectureFileRepository;
use Kytario\Model\Repository\TabRepository;
use Kytario\Service\Provider\TabProvider;
use Kytario\Service\Tab\Dto\TabConversionResult;
use Kytario\Service\Tab\Dto\TabMetadata;
use Kytario\Service\Tab\Dto\TabTrackMetadata;
use Kytario\Service\Tab\Dto\TabValidationError;
use Kytario\Service\Tab\Exception\TabValidationException;
use Kytario\Tests\Service\Provider\Fake\FakeLectureFileProvider;
use Kytario\Tests\Support\FakeTabServiceClient;
use Kytario\Tests\Support\Fixture;
use Kytario\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TabProvider::class)]
final class TabProviderTest extends IntegrationTestCase
{
	private FakeTabServiceClient $tabService;

	private FakeLectureFileProvider $fileProvider;

	private TabProvider $provider;

	protected function setUp(): void
	{
		parent::setUp();

		$tabRepository = $this->container->get(TabRepository::class);
		assert($tabRepository instanceof TabRepository);
		$fileRepository = $this->container->get(LectureFileRepository::class);
		assert($fileRepository instanceof LectureFileRepository);

		$this->tabService = new FakeTabServiceClient();
		$this->fileProvider = new FakeLectureFileProvider($fileRepository);
		$this->provider = new TabProvider($tabRepository, $this->tabService, $this->fileProvider);
	}

	public function testCreateTabValidatesAndStoresMetadata(): void
	{
		[$user, $lecture] = $this->seedLecture();

		$this->tabService->metadata = new TabMetadata('Song', 'Artist', null, 132, 2, [
			new TabTrackMetadata('Guitar', 6, ['E', 'A', 'D', 'G', 'B', 'E']),
		]);

		$tab = $this->provider->createTab($user, $lecture, 'Main riff', ':4 0.6 2.6');

		self::assertSame('Main riff', $tab->name);
		self::assertSame(TabSourceTypeEnum::Authored, $tab->sourceType);
		self::assertSame(132, $tab->tempo);
		self::assertSame(2, $tab->trackCount);
		self::assertSame('E A D G B E', $tab->tuning);
		self::assertSame([':4 0.6 2.6'], $this->tabService->validatedAlphaTex);
	}

	public function testCreateTabWithInvalidAlphaTexThrowsWithErrors(): void
	{
		[$user, $lecture] = $this->seedLecture();

		$this->tabService->validationResult = FakeTabServiceClient::invalid('Unexpected token', 2, 5);

		try {
			$this->provider->createTab($user, $lecture, 'Broken', 'not valid');
			self::fail('Expected TabValidationException.');
		} catch (TabValidationException $e) {
			self::assertCount(1, $e->getErrors());
			self::assertSame('Unexpected token', $e->getErrors()[0]->message);
			self::assertSame(2, $e->getErrors()[0]->line);
		}

		// Nothing was persisted for the invalid tab.
		self::assertCount(0, $this->provider->getTabsByLecture($lecture));
	}

	public function testImportGpFileStoresOriginalAndCreatesTab(): void
	{
		[$user, $lecture] = $this->seedLecture();

		$this->tabService->conversionResult = new TabConversionResult(
			'\\title "Imported" . :4 0.6',
			new TabMetadata('Imported', null, null, 90, 1, [new TabTrackMetadata('Gtr', 6, ['D', 'A', 'D', 'G', 'B', 'E'])]),
		);

		$tab = $this->provider->importGpFile($user, $lecture, 'Imported tab', 'song.gp5', 'RAWGPBYTES');

		self::assertSame(TabSourceTypeEnum::ImportedGp, $tab->sourceType);
		self::assertSame('\\title "Imported" . :4 0.6', $tab->alphatexContent);
		self::assertSame(90, $tab->tempo);
		self::assertSame('D A D G B E', $tab->tuning);
		self::assertNotNull($tab->originalFile);
		// The original .gp bytes were stored.
		self::assertCount(1, $this->fileProvider->findByLecture($lecture));
		self::assertSame('RAWGPBYTES', $this->fileProvider->storedBytes[$tab->originalFile->id]);
	}

	public function testImportGpFileCleansUpStoredFileWhenConversionFails(): void
	{
		[$user, $lecture] = $this->seedLecture();

		$this->tabService->conversionValidationException = new TabValidationException(
			[new TabValidationError('Unable to parse Guitar Pro file.')],
		);

		try {
			$this->provider->importGpFile($user, $lecture, 'Bad import', 'broken.gp', 'GARBAGE');
			self::fail('Expected TabValidationException.');
		} catch (TabValidationException $e) {
			self::assertCount(1, $e->getErrors());
		}

		// No orphaned file and no tab left behind.
		self::assertCount(0, $this->fileProvider->findByLecture($lecture));
		self::assertCount(0, $this->provider->getTabsByLecture($lecture));
	}

	/** @return array{0: User, 1: Lecture} */
	private function seedLecture(): array
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);
		$lecture = Fixture::createLecture($user, $course);

		return [$user, $lecture];
	}
}
