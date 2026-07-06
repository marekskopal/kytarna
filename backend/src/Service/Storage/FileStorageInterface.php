<?php

declare(strict_types=1);

namespace Kytarna\Service\Storage;

interface FileStorageInterface
{
	public function put(string $key, string $body, string $contentType): void;

	public function get(string $key): string;

	public function delete(string $key): void;
}
