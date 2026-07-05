<?php

declare(strict_types=1);

namespace Ukolio\Service\Authentication;

use JsonException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Ukolio\Service\Authentication\Dto\TokenInfoDto;
use Ukolio\Service\Authentication\Exception\GoogleAuthException;
use const JSON_THROW_ON_ERROR;

final readonly class GoogleAuthService implements GoogleAuthServiceInterface
{
	private const string TokenInfoUrl = 'https://oauth2.googleapis.com/tokeninfo';

	private HttpClientInterface $httpClient;

	public function __construct(?HttpClientInterface $httpClient = null)
	{
		$this->httpClient = $httpClient ?? HttpClient::create();
	}

	public function verifyIdToken(string $idToken): TokenInfoDto
	{
		try {
			$response = $this->httpClient->request('GET', self::TokenInfoUrl, [
				'query' => ['id_token' => $idToken],
			]);

			/** @var array{sub: string, email: string, name?: string, aud: string, email_verified?: string|bool} $payload */
			$payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

			$tokenInfo = TokenInfoDto::fromArray($payload);
		} catch (HttpClientExceptionInterface | JsonException $e) {
			throw new GoogleAuthException('Failed to verify Google ID token: ' . $e->getMessage(), previous: $e);
		}

		$expectedClientId = (string) getenv('GOOGLE_CLIENT_ID');
		if ($expectedClientId === '' || $tokenInfo->aud !== $expectedClientId) {
			throw new GoogleAuthException('Invalid audience in Google ID token', payload: $payload);
		}

		if (!$tokenInfo->emailVerified) {
			throw new GoogleAuthException('Email not verified with Google', payload: $payload);
		}

		return $tokenInfo;
	}
}
