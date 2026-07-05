<?php

declare(strict_types=1);

namespace Ukolio\Tests\Service\Script;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Ukolio\Service\Script\SecretCipher;

#[CoversClass(SecretCipher::class)]
final class SecretCipherTest extends TestCase
{
	private const string TokenKey = 'integration-test-token-key-0000000000000000';

	public function testRoundTripsArbitraryPlaintext(): void
	{
		$cipher = new SecretCipher(self::TokenKey);

		foreach (['', 'hunter2', 'unicode: žluťoučký 🦄', str_repeat('x', 4096)] as $plaintext) {
			$encrypted = $cipher->encrypt($plaintext);
			self::assertStringStartsWith('v1.', $encrypted);
			self::assertNotSame($plaintext, $encrypted);
			self::assertSame($plaintext, $cipher->decrypt($encrypted));
		}
	}

	public function testEncryptionIsNonDeterministic(): void
	{
		$cipher = new SecretCipher(self::TokenKey);

		self::assertNotSame($cipher->encrypt('same'), $cipher->encrypt('same'));
	}

	public function testDecryptRejectsTamperedCiphertext(): void
	{
		$cipher = new SecretCipher(self::TokenKey);
		$encrypted = $cipher->encrypt('secret');

		$this->expectException(RuntimeException::class);
		$cipher->decrypt($encrypted . 'A');
	}

	public function testDecryptRejectsForeignKey(): void
	{
		$encrypted = (new SecretCipher(self::TokenKey))->encrypt('secret');

		$this->expectException(RuntimeException::class);
		(new SecretCipher('a-completely-different-token-key-1111111111'))->decrypt($encrypted);
	}

	public function testRejectsEmptyTokenKey(): void
	{
		$this->expectException(RuntimeException::class);
		new SecretCipher('');
	}
}
