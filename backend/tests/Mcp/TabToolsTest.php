<?php

declare(strict_types=1);

namespace Kytarna\Tests\Mcp;

use Kytarna\Mcp\McpUserContextInterface;
use Kytarna\Mcp\Tool\TabTools;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Repository\LectureFileRepository;
use Kytarna\Model\Repository\TabRepository;
use Kytarna\Service\Provider\LectureProviderInterface;
use Kytarna\Service\Provider\TabProvider;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Kytarna\Service\Tab\Dto\TabConversionResult;
use Kytarna\Service\Tab\Dto\TabMetadata;
use Kytarna\Tests\Service\Provider\Fake\FakeLectureFileProvider;
use Kytarna\Tests\Support\FakeTabServiceClient;
use Kytarna\Tests\Support\Fixture;
use Kytarna\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TabTools::class)]
final class TabToolsTest extends IntegrationTestCase
{
	private FakeTabServiceClient $tabService;

	protected function setUp(): void
	{
		parent::setUp();

		$this->tabService = new FakeTabServiceClient();
	}

	public function testCreateTabReturnsTabOnValidAlphaTex(): void
	{
		[$tools, $lecture] = $this->boot();

		$result = $tools->createTab($lecture->id, 'Riff', ':4 0.6 2.6');

		self::assertTrue($result->valid);
		self::assertNotNull($result->tab);
		self::assertSame('Riff', $result->tab->name);
		self::assertSame([], $result->errors);
	}

	public function testCreateTabReturnsErrorsOnInvalidAlphaTexWithoutThrowing(): void
	{
		[$tools, $lecture] = $this->boot();
		$this->tabService->validationResult = FakeTabServiceClient::invalid('Bad token', 3, 7);

		$result = $tools->createTab($lecture->id, 'Broken', 'nope');

		self::assertFalse($result->valid);
		self::assertNull($result->tab);
		self::assertCount(1, $result->errors);
		self::assertSame('Bad token', $result->errors[0]['message']);
		self::assertSame(3, $result->errors[0]['line']);
	}

	public function testImportGpFileHappyPath(): void
	{
		[$tools, $lecture] = $this->boot();
		$this->tabService->conversionResult = new TabConversionResult(
			'\\title "X" . :4 0.6',
			new TabMetadata('X', null, null, 110, 1, []),
		);

		$result = $tools->importGpFile($lecture->id, 'Imported', base64_encode('RAWGP'));

		self::assertTrue($result->valid);
		self::assertNotNull($result->tab);
		self::assertSame('imported_gp', $result->tab->sourceType);
		self::assertSame(110, $result->tab->tempo);
		self::assertNotNull($result->tab->originalFileId);

		// It shows up in list_tabs.
		self::assertCount(1, $tools->listTabs($lecture->id)->tabs);
	}

	/** @return array{0: TabTools, 1: Lecture} */
	private function boot(): array
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);
		$lecture = Fixture::createLecture($user, $course);

		$ctx = $this->container->get(McpUserContextInterface::class);
		assert($ctx instanceof McpUserContextInterface);
		$ctx->setUser($user);

		$tabRepository = $this->container->get(TabRepository::class);
		assert($tabRepository instanceof TabRepository);
		$fileRepository = $this->container->get(LectureFileRepository::class);
		assert($fileRepository instanceof LectureFileRepository);
		$lectureProvider = $this->container->get(LectureProviderInterface::class);
		assert($lectureProvider instanceof LectureProviderInterface);
		$workspaceProvider = $this->container->get(WorkspaceProviderInterface::class);
		assert($workspaceProvider instanceof WorkspaceProviderInterface);

		$tabProvider = new TabProvider($tabRepository, $this->tabService, new FakeLectureFileProvider($fileRepository));

		$tools = new TabTools($ctx, $lectureProvider, $tabProvider, $workspaceProvider);

		return [$tools, $lecture];
	}
}
