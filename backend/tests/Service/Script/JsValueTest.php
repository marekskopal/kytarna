<?php

declare(strict_types=1);

namespace Kytario\Tests\Service\Script;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Kytario\Service\Script\Host\JsValue;

#[CoversClass(JsValue::class)]
final class JsValueTest extends TestCase
{
	public function testToAssocHandlesStdClassAndArrayAndScalar(): void
	{
		$obj = new stdClass();
		$obj->a = 1;
		$obj->b = 'x';

		self::assertSame(['a' => 1, 'b' => 'x'], JsValue::toAssoc($obj));
		self::assertSame(['k' => 'v'], JsValue::toAssoc(['k' => 'v']));
		self::assertSame([], JsValue::toAssoc('not-an-object'));
		self::assertSame([], JsValue::toAssoc(null));
	}

	public function testToAssocHandlesNonStdClassObjects(): void
	{
		// Some ext-v8js builds marshal JS objects as a V8Object instance rather than stdClass;
		// any object with public members must still be read via get_object_vars().
		$jsLike = new class {
			public int $statusId = 6;

			public int $limit = 200;
		};

		self::assertSame(['statusId' => 6, 'limit' => 200], JsValue::toAssoc($jsLike));
	}

	public function testIntListHandlesNonStdClassObjects(): void
	{
		$jsArrayLike = new class {
			public int $a = 1;

			public string $b = '2';
		};

		self::assertSame([1, 2], JsValue::intList($jsArrayLike));
	}

	public function testStringCoercion(): void
	{
		self::assertSame('hi', JsValue::string('hi'));
		self::assertSame('42', JsValue::string(42));
		self::assertNull(JsValue::string(null));
		self::assertSame('fallback', JsValue::string(['x'], 'fallback'));
	}

	public function testIntCoercion(): void
	{
		self::assertSame(7, JsValue::int(7));
		self::assertSame(7, JsValue::int('7'));
		self::assertSame(-7, JsValue::int('-7'));
		self::assertSame(3, JsValue::int(3.9));
		self::assertNull(JsValue::int('abc'));
		self::assertNull(JsValue::int(null));
	}

	public function testIntListFiltersNonIntegers(): void
	{
		self::assertSame([1, 2, 3], JsValue::intList([1, '2', 3.0]));
		self::assertSame([5], JsValue::intList([5, 'x', null]));
		self::assertSame([], JsValue::intList('not-a-list'));
	}
}
