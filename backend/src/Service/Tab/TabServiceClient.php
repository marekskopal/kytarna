<?php

declare(strict_types=1);

namespace Kytarna\Service\Tab;

use JsonException;
use Kytarna\Service\Tab\Dto\TabConversionResult;
use Kytarna\Service\Tab\Dto\TabMetadata;
use Kytarna\Service\Tab\Dto\TabValidationError;
use Kytarna\Service\Tab\Dto\TabValidationResult;
use Kytarna\Service\Tab\Exception\TabServiceException;
use Kytarna\Service\Tab\Exception\TabValidationException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use const JSON_THROW_ON_ERROR;

final readonly class TabServiceClient implements TabServiceClientInterface
{
	private string $baseUrl;

	private HttpClientInterface $httpClient;

	public function __construct(?string $baseUrl = null, ?HttpClientInterface $httpClient = null)
	{
		$url = $baseUrl ?? (string) getenv('TAB_SERVICE_URL');
		if ($url === '') {
			$url = 'http://tab-service:8080';
		}
		$this->baseUrl = rtrim($url, '/');
		$this->httpClient = $httpClient ?? HttpClient::create();
	}

	public function validate(string $alphaTex): TabValidationResult
	{
		$payload = $this->requestJson('POST', '/validate', [
			'headers' => ['Content-Type' => 'application/json'],
			'body' => json_encode(['alphaTex' => $alphaTex], JSON_THROW_ON_ERROR),
		], allowedStatuses: [200, 400]);

		$valid = ($payload['valid'] ?? false) === true;
		$metadata = isset($payload['metadata']) && is_array($payload['metadata'])
			? TabMetadata::fromArray($payload['metadata'])
			: null;

		return new TabValidationResult($valid, self::parseErrors($payload), $metadata);
	}

	public function convert(string $bytes): TabConversionResult
	{
		$payload = $this->requestJson('POST', '/convert', [
			'headers' => ['Content-Type' => 'application/octet-stream'],
			'body' => $bytes,
		], allowedStatuses: [200, 422]);

		if (!isset($payload['alphaTex']) || !is_string($payload['alphaTex'])) {
			$errors = self::parseErrors($payload);

			throw new TabValidationException(
				$errors !== [] ? $errors : [new TabValidationError('Unable to convert Guitar Pro file.')],
				'Guitar Pro conversion failed.',
			);
		}

		$metadata = isset($payload['metadata']) && is_array($payload['metadata'])
			? TabMetadata::fromArray($payload['metadata'])
			: new TabMetadata(null, null, null, null, null, []);

		return new TabConversionResult($payload['alphaTex'], $metadata);
	}

	/**
	 * @param array<string, mixed> $options
	 * @param list<int> $allowedStatuses
	 * @return array<mixed, mixed>
	 */
	private function requestJson(string $method, string $path, array $options, array $allowedStatuses): array
	{
		try {
			$response = $this->httpClient->request($method, $this->baseUrl . $path, $options);
			$status = $response->getStatusCode();
			$content = $response->getContent(throw: false);
		} catch (HttpClientExceptionInterface $e) {
			throw new TabServiceException('tab-service request failed: ' . $e->getMessage(), previous: $e);
		}

		if (!in_array($status, $allowedStatuses, true)) {
			throw new TabServiceException(sprintf('tab-service returned unexpected status %d.', $status));
		}

		try {
			$decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new TabServiceException('tab-service returned invalid JSON: ' . $e->getMessage(), previous: $e);
		}

		return is_array($decoded) ? $decoded : [];
	}

	/**
	 * @param array<mixed, mixed> $payload
	 * @return list<TabValidationError>
	 */
	private static function parseErrors(array $payload): array
	{
		if (!isset($payload['errors']) || !is_array($payload['errors'])) {
			return [];
		}
		$errors = [];
		foreach ($payload['errors'] as $error) {
			if (is_array($error)) {
				$errors[] = TabValidationError::fromArray($error);
			}
		}
		return $errors;
	}
}
