<?php

declare(strict_types=1);

namespace Kytario\App;

use Kytario\App\Bootstrap\EnvironmentValidator;
use Kytario\App\ServiceProvider\AuthenticationServiceProvider;
use Kytario\App\ServiceProvider\DomainServiceProvider;
use Kytario\App\ServiceProvider\InfrastructureServiceProvider;
use Kytario\App\ServiceProvider\OrmServiceProvider;
use Kytario\Middleware\AuthorizationMiddleware;
use Kytario\Middleware\CorsMiddleware;
use Kytario\Route\Strategy\JsonStrategy;
use Kytario\Service\Dbal\DbContext;
use League\Container\Container;
use League\Container\ReflectionContainer;
use MarekSkopal\Router\Builder\RouterBuilder;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;

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
