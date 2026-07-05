<?php

declare(strict_types=1);

namespace Ukolio\Service\Authentication\Exception;

use RuntimeException;
use Throwable;

final class GoogleAuthException extends RuntimeException
{
	/** @param array<string,mixed>|null $payload */
	public function __construct(string $message, public readonly ?array $payload = null, ?Throwable $previous = null,)
	{
		parent::__construct($message, 0, $previous);
	}
}
