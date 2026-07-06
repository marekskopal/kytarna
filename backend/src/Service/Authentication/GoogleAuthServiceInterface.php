<?php

declare(strict_types=1);

namespace Kytarna\Service\Authentication;

use Kytarna\Service\Authentication\Dto\TokenInfoDto;

interface GoogleAuthServiceInterface
{
	public function verifyIdToken(string $idToken): TokenInfoDto;
}
