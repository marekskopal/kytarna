<?php

declare(strict_types=1);

namespace Kytario\Service\Script;

interface SecretCipherInterface
{
	public function encrypt(string $plaintext): string;

	public function decrypt(string $ciphertext): string;
}
