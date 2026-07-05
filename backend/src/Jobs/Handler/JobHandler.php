<?php

declare(strict_types=1);

namespace Ukolio\Jobs\Handler;

use Ukolio\Jobs\Message\ReceivedMessageInterface;

interface JobHandler
{
	public function handle(ReceivedMessageInterface $message): void;
}
