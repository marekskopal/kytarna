<?php

declare(strict_types=1);

namespace Kytarna\App;

use Kytarna\Service\Dbal\DbContext;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class Application
{
	public function __construct(public ContainerInterface $container, public RequestHandlerInterface $handler, public DbContext $dbContext,)
	{
	}
}
