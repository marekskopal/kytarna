<?php

declare(strict_types=1);

namespace Kytario\Service\Authentication;

use Kytario\Dto\AuthenticationDto;
use Kytario\Dto\CredentialsDto;
use Kytario\Model\Entity\User;

interface AuthenticationServiceInterface
{
	public const string TokenAlgorithm = 'HS256';

	public const string TokenTypeAccess = 'access';

	public const string TokenTypeRefresh = 'refresh';

	public function authenticate(CredentialsDto $credentials): AuthenticationDto;

	public function createAuthentication(User $user): AuthenticationDto;
}
