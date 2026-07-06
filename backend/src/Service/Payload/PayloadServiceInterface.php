<?php

declare(strict_types=1);

namespace Kytarna\Service\Payload;

use Kytarna\Dto\ArrayFactoryInterface;
use Kytarna\Jobs\Message\ReceivedMessageInterface;

interface PayloadServiceInterface
{
	/**
	 * @param class-string<T> $dtoClass
	 * @return T
	 * @template T of ArrayFactoryInterface
	 */
	public function getPayloadDto(ReceivedMessageInterface $message, string $dtoClass): object;
}
