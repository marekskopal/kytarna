<?php

declare(strict_types=1);

namespace Kytario\Tests\Validator;

use Kytario\Validator\TextFieldValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TextFieldValidator::class)]
final class TextFieldValidatorTest extends TestCase
{
	public function testValidNameIsTrimmed(): void
	{
		self::assertSame('My Lecture', TextFieldValidator::validateName('  My Lecture  ', 'Lecture'));
	}

	public function testEmptyNameIsRejected(): void
	{
		try {
			TextFieldValidator::validateName('   ', 'Lecture');
			self::fail('Expected RuntimeException.');
		} catch (RuntimeException $e) {
			self::assertSame('Lecture name is required.', $e->getMessage());
			self::assertSame(422, $e->getCode());
		}
	}

	public function testOverlongNameIsRejected(): void
	{
		try {
			TextFieldValidator::validateName(str_repeat('a', TextFieldValidator::MaxNameLength + 1), 'Course');
			self::fail('Expected RuntimeException.');
		} catch (RuntimeException $e) {
			self::assertStringContainsString('Course name is too long', $e->getMessage());
			self::assertSame(422, $e->getCode());
		}
	}

	public function testMaxLengthNameIsAccepted(): void
	{
		$name = str_repeat('a', TextFieldValidator::MaxNameLength);
		self::assertSame($name, TextFieldValidator::validateName($name, 'Lecture'));
	}

	public function testNullDescriptionIsAccepted(): void
	{
		self::assertNull(TextFieldValidator::validateDescription(null));
	}

	public function testOverlongDescriptionIsRejected(): void
	{
		try {
			TextFieldValidator::validateDescription(str_repeat('a', TextFieldValidator::MaxDescriptionLength + 1));
			self::fail('Expected RuntimeException.');
		} catch (RuntimeException $e) {
			self::assertStringContainsString('Description is too long', $e->getMessage());
			self::assertSame(422, $e->getCode());
		}
	}

	public function testMaxLengthDescriptionIsAccepted(): void
	{
		$description = str_repeat('a', TextFieldValidator::MaxDescriptionLength);
		self::assertSame($description, TextFieldValidator::validateDescription($description));
	}
}
