<?php

declare(strict_types=1);

namespace Kytarna\Tests\OAuth;

use Kytarna\OAuth\PkceVerifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PkceVerifier::class)]
final class PkceVerifierTest extends TestCase
{
	public function testVerifyAcceptsMatchingS256Challenge(): void
	{
		// RFC 7636 sample
		$verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
		$challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

		self::assertTrue(new PkceVerifier()->verify($verifier, $challenge));
	}

	public function testVerifyRejectsMismatch(): void
	{
		self::assertFalse(new PkceVerifier()->verify('abc', 'something-else'));
	}

	public function testVerifyIsLengthSensitive(): void
	{
		$verifier = 'short';
		$correct = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

		self::assertFalse(new PkceVerifier()->verify($verifier, $correct . 'x'));
	}
}
