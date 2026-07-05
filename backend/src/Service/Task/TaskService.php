<?php

declare(strict_types=1);

namespace Kytario\Service\Task;

use JsonException;
use RuntimeException;
use Kytario\Dto\ArrayFactoryInterface;
use Kytario\Jobs\Message\ReceivedMessageInterface;
use const JSON_THROW_ON_ERROR;

final readonly class TaskService implements TaskServiceInterface
{
	/**
	 * @param class-string<T> $dtoClass
	 * @return T
	 * @template T of ArrayFactoryInterface
	 */
	public function getPayloadDto(ReceivedMessageInterface $message, string $dtoClass): object
	{
		try {
			/** @var array<string, mixed> $decoded */
			$decoded = json_decode($message->getPayload(), true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new RuntimeException(
				'Failed to decode queue message payload for ' . $dtoClass . ': ' . $e->getMessage(),
				0,
				$e,
			);
		}

		return $dtoClass::fromArray($decoded);
	}
}
