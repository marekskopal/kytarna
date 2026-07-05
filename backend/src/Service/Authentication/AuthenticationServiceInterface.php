<?php

declare(strict_types=1);

namespace Ukolio\Service\Authentication;

use Ukolio\Dto\AuthenticationDto;
use Ukolio\Dto\CredentialsDto;
use Ukolio\Model\Entity\User;

interface AuthenticationServiceInterface
{
	public const string TokenAlgorithm = 'HS256';

	public const string TokenTypeAccess = 'access';

	public const string TokenTypeRefresh = 'refresh';

	public function authenticate(CredentialsDto $credentials): AuthenticationDto;

	public function createAuthentication(User $user): AuthenticationDto;
}
