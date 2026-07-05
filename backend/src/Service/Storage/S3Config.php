<?php

declare(strict_types=1);

namespace Ukolio\Service\Storage;

final readonly class S3Config
{
	public function __construct(
		public string $endpoint,
		public string $region,
		public string $bucket,
		public string $accessKey,
		public string $secretKey,
		public bool $pathStyleEndpoint,
		public int $maxFileSizeBytes,
	) {
	}

	public static function fromEnv(): self
	{
		$endpoint = (string) getenv('S3_ENDPOINT');
		$region = (string) getenv('S3_REGION');
		if ($region === '') {
			$region = 'us-east-1';
		}
		$bucket = (string) getenv('S3_BUCKET');
		$accessKey = (string) getenv('S3_ACCESS_KEY');
		$secretKey = (string) getenv('S3_SECRET_KEY');

		$pathStyle = strtolower((string) getenv('S3_USE_PATH_STYLE'));
		$pathStyleEndpoint = $pathStyle === '1' || $pathStyle === 'true' || $pathStyle === 'yes';

		$maxMbRaw = (string) getenv('TASK_FILE_MAX_SIZE_MB');
		$maxMb = $maxMbRaw === '' ? 25 : (int) $maxMbRaw;
		if ($maxMb <= 0) {
			$maxMb = 25;
		}

		return new self(
			endpoint: $endpoint,
			region: $region,
			bucket: $bucket,
			accessKey: $accessKey,
			secretKey: $secretKey,
			pathStyleEndpoint: $pathStyleEndpoint,
			maxFileSizeBytes: $maxMb * 1024 * 1024,
		);
	}
}
