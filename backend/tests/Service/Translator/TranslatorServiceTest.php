<?php

declare(strict_types=1);

namespace Kytario\Tests\Service\Translator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Kytario\Model\Entity\Enum\LocaleEnum;
use Kytario\Service\Translator\TranslatorService;

#[CoversClass(TranslatorService::class)]
final class TranslatorServiceTest extends TestCase
{
	private string $dir;

	protected function setUp(): void
	{
		$this->dir = sys_get_temp_dir() . '/kytario-translator-' . uniqid();
		mkdir($this->dir);
		file_put_contents(
			$this->dir . '/en.json',
			(string) json_encode([
				'email' => [
					'subject' => ['invitation' => 'Invite to {workspace}'],
					'invitation' => ['greeting' => 'Hi', 'cta' => 'Accept'],
				],
				'only_in_en' => 'English-only value',
			]),
		);
		file_put_contents(
			$this->dir . '/cs.json',
			(string) json_encode([
				'email' => [
					'subject' => ['invitation' => 'Pozvánka do {workspace}'],
					'invitation' => ['greeting' => 'Ahoj'],
				],
			]),
		);
	}

	protected function tearDown(): void
	{
		$files = glob($this->dir . '/*');
		if ($files !== false) {
			array_map('unlink', $files);
		}
		rmdir($this->dir);
	}

	public function testTranslateReturnsLocaleSpecificValue(): void
	{
		$svc = new TranslatorService($this->dir);
		self::assertSame('Invite to {workspace}', $svc->translate('email.subject.invitation', LocaleEnum::En));
		self::assertSame('Pozvánka do {workspace}', $svc->translate('email.subject.invitation', LocaleEnum::Cs));
	}

	public function testTranslateFallsBackToEnglishForMissingKeys(): void
	{
		$svc = new TranslatorService($this->dir);
		self::assertSame('English-only value', $svc->translate('only_in_en', LocaleEnum::Cs));
	}

	public function testUnknownKeyReturnsKeyItself(): void
	{
		$svc = new TranslatorService($this->dir);
		self::assertSame('nope.nope', $svc->translate('nope.nope', LocaleEnum::En));
	}

	public function testSectionReturnsArrayMap(): void
	{
		$svc = new TranslatorService($this->dir);
		$section = $svc->section('email.invitation', LocaleEnum::Cs);
		self::assertSame('Ahoj', $section['greeting']);
	}

	public function testSectionFallsBackToEnglishWhenLocaleDoesNotHaveIt(): void
	{
		$svc = new TranslatorService($this->dir);
		$section = $svc->section('email.invitation', LocaleEnum::Cs);
		// Czech locale only has 'greeting'; section should NOT mix in English keys.
		self::assertArrayNotHasKey('cta', $section);

		// But a section that doesn't exist in Czech at all should fall back fully.
		// Simulate by querying a section only in EN.
		file_put_contents(
			$this->dir . '/en.json',
			(string) json_encode([
				'onlyEnSection' => ['a' => '1', 'b' => '2'],
			]),
		);
		$svc2 = new TranslatorService($this->dir);
		$fallback = $svc2->section('onlyEnSection', LocaleEnum::Cs);
		self::assertSame(['a' => '1', 'b' => '2'], $fallback);
	}
}
