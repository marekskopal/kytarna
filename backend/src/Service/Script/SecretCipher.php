<?php

declare(strict_types=1);

namespace Ukolio\Service\Script;

use RuntimeException;
use SensitiveParameter;
use const OPENSSL_RAW_DATA;

/**
 * Authenticated symmetric encryption (AES-256-GCM) for script secret variables.
 *
 * The key is derived from AUTHORIZATION_TOKEN_KEY (the same secret that signs JWTs), so no
 * extra key material has to be provisioned. Ciphertext format is "v1." followed by
 * base64(iv ‖ tag ‖ ciphertext); the version prefix lets the scheme evolve without ambiguity.
 */
final readonly class SecretCipher implements SecretCipherInterface
{
	private const string Cipher = 'aes-256-gcm';
	private const string Version = 'v1.';
	private const int IvLength = 12;
	private const int TagLength = 16;

	/** @var non-empty-string */
	private string $key;

	public function __construct(#[SensitiveParameter] string $tokenKey)
	{
		if ($tokenKey === '') {
			throw new RuntimeException('SecretCipher requires a non-empty AUTHORIZATION_TOKEN_KEY.');
		}

		// Hash the token key to a fixed 32-byte AES-256 key regardless of its length.
		$this->key = hash('sha256', 'ukolio-script-secret:' . $tokenKey, true);
	}

	public function encrypt(#[SensitiveParameter] string $plaintext): string
	{
		$iv = random_bytes(self::IvLength);
		$tag = '';

		$ciphertext = openssl_encrypt($plaintext, self::Cipher, $this->key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TagLength);
		if ($ciphertext === false) {
			throw new RuntimeException('Failed to encrypt secret value.');
		}

		return self::Version . base64_encode($iv . $tag . $ciphertext);
	}

	public function decrypt(#[SensitiveParameter] string $ciphertext): string
	{
		if (!str_starts_with($ciphertext, self::Version)) {
			throw new RuntimeException('Unrecognised secret ciphertext format.');
		}

		$raw = base64_decode(substr($ciphertext, strlen(self::Version)), true);
		if ($raw === false || strlen($raw) < self::IvLength + self::TagLength) {
			throw new RuntimeException('Corrupt secret ciphertext.');
		}

		$iv = substr($raw, 0, self::IvLength);
		$tag = substr($raw, self::IvLength, self::TagLength);
		$payload = substr($raw, self::IvLength + self::TagLength);

		$plaintext = openssl_decrypt($payload, self::Cipher, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
		if ($plaintext === false) {
			throw new RuntimeException('Failed to decrypt secret value (authentication failed).');
		}

		return $plaintext;
	}
}
