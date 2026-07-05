<?php

declare(strict_types=1);

namespace Ukolio\Service\Authentication;

use Ukolio\Service\Authentication\Dto\TokenInfoDto;

interface GoogleAuthServiceInterface
{
	public function verifyIdToken(string $idToken): TokenInfoDto;
}
