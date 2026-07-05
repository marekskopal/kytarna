<?php

declare(strict_types=1);

namespace Kytario\OAuth;

use Kytario\Model\Entity\OAuthClient;

interface ClientServiceInterface
{
	public function findByClientId(string $clientId): ?OAuthClient;

	public function validateRedirectUri(string $clientId, string $redirectUri): bool;

	/** @param list<string> $redirectUris */
	public function registerClient(string $clientName, array $redirectUris): OAuthClient;
}
