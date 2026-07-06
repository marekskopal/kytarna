<?php

declare(strict_types=1);

namespace Kytarna\Service\Storage;

use AsyncAws\S3\S3Client;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final class S3FileStorage implements FileStorageInterface
{
	private bool $bucketEnsured = false;

	public function __construct(
		private readonly S3Client $client,
		private readonly S3Config $config,
		private readonly LoggerInterface $logger,
	) {
	}

	public function put(string $key, string $body, string $contentType): void
	{
		$this->ensureBucket();
		$this->client->putObject([
			'Bucket' => $this->config->bucket,
			'Key' => $key,
			'Body' => $body,
			'ContentType' => $contentType,
		]);
	}

	public function get(string $key): string
	{
		$this->ensureBucket();

		try {
			$result = $this->client->getObject([
				'Bucket' => $this->config->bucket,
				'Key' => $key,
			]);
			return $result->getBody()->getContentAsString();
		} catch (Throwable $e) {
			throw new RuntimeException('Failed to read object "' . $key . '": ' . $e->getMessage(), 0, $e);
		}
	}

	public function delete(string $key): void
	{
		$this->ensureBucket();

		try {
			$this->client->deleteObject([
				'Bucket' => $this->config->bucket,
				'Key' => $key,
			]);
		} catch (Throwable $e) {
			$this->logger->warning('Failed to delete S3 object "' . $key . '": ' . $e->getMessage());
		}
	}

	private function ensureBucket(): void
	{
		if ($this->bucketEnsured) {
			return;
		}

		try {
			$exists = $this->client->bucketExists(['Bucket' => $this->config->bucket]);
			if (!$exists->isSuccess()) {
				$this->client->createBucket(['Bucket' => $this->config->bucket]);
			}
		} catch (Throwable $e) {
			throw new RuntimeException(
				'Failed to ensure S3 bucket "' . $this->config->bucket . '": ' . $e->getMessage(),
				0,
				$e,
			);
		}

		$this->bucketEnsured = true;
	}
}
