<?php

declare(strict_types=1);

namespace Kytarna\Jobs\Handler;

use Kytarna\Jobs\Message\ReceivedMessageInterface;

interface JobHandler
{
	public function handle(ReceivedMessageInterface $message): void;
}
