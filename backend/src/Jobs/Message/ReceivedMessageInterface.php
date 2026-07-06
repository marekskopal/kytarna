<?php

declare(strict_types=1);

namespace Kytarna\Jobs\Message;

interface ReceivedMessageInterface
{
	public function getPayload(): string;

	public function getQueue(): string;
}
