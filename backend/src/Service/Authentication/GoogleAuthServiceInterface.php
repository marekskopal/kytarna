<?php

declare(strict_types=1);

namespace Kytario\Service\Authentication;

use Kytario\Service\Authentication\Dto\TokenInfoDto;

interface GoogleAuthServiceInterface
{
	public function verifyIdToken(string $idToken): TokenInfoDto;
}
