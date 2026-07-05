<?php

declare(strict_types=1);

namespace Kytario\Service\Payload;

use Kytario\Dto\ArrayFactoryInterface;
use Kytario\Jobs\Message\ReceivedMessageInterface;

interface PayloadServiceInterface
{
	/**
	 * @param class-string<T> $dtoClass
	 * @return T
	 * @template T of ArrayFactoryInterface
	 */
	public function getPayloadDto(ReceivedMessageInterface $message, string $dtoClass): object;
}
