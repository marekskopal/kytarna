<?php

declare(strict_types=1);

namespace Ukolio\Service\Script\Host;

use JsonException;
use RuntimeException;
use const JSON_THROW_ON_ERROR;

/**
 * Result of ukolio.fetch(), exposed to JS as { status, headers, json(), text() }.
 */
final readonly class HttpResponse
{
	/** @param array<string, string> $headers */
	public function __construct(public int $status, public array $headers, private string $body,)
	{
	}

	public function text(): string
	{
		return $this->body;
	}

	public function json(): mixed
	{
		try {
			return json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new RuntimeException('Response body is not valid JSON: ' . $e->getMessage(), 0, $e);
		}
	}
}
