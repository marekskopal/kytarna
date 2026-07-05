<?php

declare(strict_types=1);

namespace Kytario\Jobs\Handler;

use Kytario\Jobs\Message\ReceivedMessageInterface;

interface JobHandler
{
	public function handle(ReceivedMessageInterface $message): void;
}
