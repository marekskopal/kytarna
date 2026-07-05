<?php

declare(strict_types=1);

namespace Ukolio\Service\Task;

use Ukolio\Dto\ArrayFactoryInterface;
use Ukolio\Jobs\Message\ReceivedMessageInterface;

interface TaskServiceInterface
{
	/**
	 * @param class-string<T> $dtoClass
	 * @return T
	 * @template T of ArrayFactoryInterface
	 */
	public function getPayloadDto(ReceivedMessageInterface $message, string $dtoClass): object;
}
