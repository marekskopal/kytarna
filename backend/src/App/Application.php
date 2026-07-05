<?php

declare(strict_types=1);

namespace Ukolio\App;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ukolio\Service\Dbal\DbContext;

final readonly class Application
{
	public function __construct(public ContainerInterface $container, public RequestHandlerInterface $handler, public DbContext $dbContext,)
	{
	}
}
