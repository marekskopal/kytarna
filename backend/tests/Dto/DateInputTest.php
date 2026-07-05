<?php

declare(strict_types=1);

namespace Kytario\Tests\Dto;

use Kytario\Dto\DateInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(DateInput::class)]
final class DateInputTest extends TestCase
{
	public function testNullAndEmptyStringMeanNoDate(): void
	{
		self::assertNull(DateInput::parse(null, 'dueDate'));
		self::assertNull(DateInput::parse('', 'dueDate'));
	}

	public function testValidDateParses(): void
	{
		$date = DateInput::parse('2026-05-10', 'dueDate');
		self::assertNotNull($date);
		self::assertSame('2026-05-10', $date->format('Y-m-d'));
	}

	/** @return list<array{mixed}> */
	public static function invalidProvider(): array
	{
		return [
			['not-a-date'],
			['tomorrow'],
			['2026-13-99'],
			['2026-05-10T10:00:00Z'],
			['2026/05/10'],
			[123],
			[['2026-05-10']],
		];
	}

	#[DataProvider('invalidProvider')]
	public function testInvalidInputThrows(mixed $value): void
	{
		$this->expectException(RuntimeException::class);
		DateInput::parse($value, 'dueDate');
	}
}
