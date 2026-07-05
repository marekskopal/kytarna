<?php

declare(strict_types=1);

namespace Kytario\Tests\Validator;

use Kytario\Validator\PasswordValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(PasswordValidator::class)]
final class PasswordValidatorTest extends TestCase
{
	#[TestWith(['Passw0rd', true])]
	#[TestWith(['Passw0rd!', true])]
	#[TestWith(['short1A', false])]
	#[TestWith(['passw0rd', false])]
	#[TestWith(['PASSW0RD', false])]
	#[TestWith(['Password', false])]
	public function testIsValid(string $password, bool $expected): void
	{
		self::assertSame($expected, PasswordValidator::isValid($password));
	}
}
