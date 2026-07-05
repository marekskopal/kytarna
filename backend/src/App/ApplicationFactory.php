<?php

declare(strict_types=1);

namespace Ukolio\App;

use League\Container\Container;
use League\Container\ReflectionContainer;
use MarekSkopal\Router\Builder\RouterBuilder;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ukolio\App\Bootstrap\EnvironmentValidator;
use Ukolio\App\ServiceProvider\AuthenticationServiceProvider;
use Ukolio\App\ServiceProvider\DomainServiceProvider;
use Ukolio\App\ServiceProvider\InfrastructureServiceProvider;
use Ukolio\App\ServiceProvider\OrmServiceProvider;
use Ukolio\App\ServiceProvider\RealtimeServiceProvider;
use Ukolio\Middleware\AuthorizationMiddleware;
use Ukolio\Middleware\CorsMiddleware;
use Ukolio\Route\Strategy\JsonStrategy;
use Ukolio\Service\Dbal\DbContext;

final readonly class ApplicationFactory
{
	public static function create(): Application
	{
		EnvironmentValidator::fromGlobals()->validate();

		$dbContext = self::initializeDbContext();
		$container = self::initializeContainer($dbContext);
		$requestHandler = self::initializeRequestHandler($container);

		return new Application($container, $requestHandler, $dbContext);
	}

	private static function initializeContainer(DbContext $dbContext): ContainerInterface
	{
		$container = new Container();
		$container->defaultToShared();
		$container->delegate(new ReflectionContainer(true));

		$container->addServiceProvider(new InfrastructureServiceProvider());
		$container->addServiceProvider(new OrmServiceProvider($dbContext));
		$container->addServiceProvider(new AuthenticationServiceProvider());
		$container->addServiceProvider(new RealtimeServiceProvider());
		$container->addServiceProvider(new DomainServiceProvider());

		return $container;
	}

	private static function initializeRequestHandler(ContainerInterface $container): RequestHandlerInterface
	{
		$strategy = $container->get(JsonStrategy::class);
		if (!$strategy instanceof JsonStrategy) {
			throw new \RuntimeException('JsonStrategy not found in container.');
		}
		$strategy->setContainer($container);

		$router = (new RouterBuilder())
			->setClassDirectories([__DIR__ . '/../Controller'])
			->build();

		$router->setStrategy($strategy);

		$corsMiddleware = $container->get(CorsMiddleware::class);
		if (!$corsMiddleware instanceof CorsMiddleware) {
			throw new \RuntimeException('CorsMiddleware not found in container.');
		}
		$router->middleware($corsMiddleware);

		$authorizationMiddleware = $container->get(AuthorizationMiddleware::class);
		if (!$authorizationMiddleware instanceof AuthorizationMiddleware) {
			throw new \RuntimeException('AuthorizationMiddleware not found in container.');
		}
		$router->middleware($authorizationMiddleware);

		return $router;
	}

	private static function initializeDbContext(): DbContext
	{
		/** @var non-empty-string $host */
		$host = (string) getenv('MYSQL_HOST');
		/** @var non-empty-string $database */
		$database = (string) getenv('MYSQL_DATABASE');
		/** @var non-empty-string $user */
		$user = (string) getenv('MYSQL_USER');
		/** @var non-empty-string $password */
		$password = (string) getenv('MYSQL_PASSWORD');

		return new DbContext($host, $database, $user, $password);
	}
}
