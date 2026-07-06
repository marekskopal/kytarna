<?php

declare(strict_types=1);

namespace Kytarna\App\ServiceProvider;

use AsyncAws\S3\S3Client;
use Http\Discovery\Psr17FactoryDiscovery;
use Kytarna\Service\Cache\CacheFactory;
use Kytarna\Service\Cache\CacheFactoryInterface;
use Kytarna\Service\Cors\CorsPolicy;
use Kytarna\Service\Logger\Logger;
use Kytarna\Service\Queue\QueuePublisher;
use Kytarna\Service\Storage\FileStorageInterface;
use Kytarna\Service\Storage\S3Config;
use Kytarna\Service\Storage\S3FileStorage;
use Kytarna\Service\Tab\TabServiceClient;
use Kytarna\Service\Tab\TabServiceClientInterface;
use League\Container\ServiceProvider\AbstractServiceProvider;
use Predis\Client;
use Predis\ClientInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;

final class InfrastructureServiceProvider extends AbstractServiceProvider
{
	public function provides(string $id): bool
	{
		return in_array($id, [
			LoggerInterface::class,
			ResponseFactoryInterface::class,
			S3Config::class,
			S3Client::class,
			FileStorageInterface::class,
			ClientInterface::class,
			CacheFactoryInterface::class,
			CorsPolicy::class,
			QueuePublisher::class,
			TabServiceClientInterface::class,
		], true);
	}

	public function register(): void
	{
		$container = $this->getContainer();

		$container->add(LoggerInterface::class, fn (): LoggerInterface => Logger::initLogger(__DIR__ . '/../../../log'));

		$container->add(
			ResponseFactoryInterface::class,
			fn (): ResponseFactoryInterface => Psr17FactoryDiscovery::findResponseFactory(),
		);

		$container->add(S3Config::class, static fn (): S3Config => S3Config::fromEnv());

		$container->add(S3Client::class, static function () use ($container): S3Client {
			$config = $container->get(S3Config::class);
			assert($config instanceof S3Config);
			$options = [
				'accessKeyId' => $config->accessKey,
				'accessKeySecret' => $config->secretKey,
				'region' => $config->region,
				'pathStyleEndpoint' => $config->pathStyleEndpoint ? 'true' : 'false',
			];
			if ($config->endpoint !== '') {
				$options['endpoint'] = $config->endpoint;
			}
			return new S3Client($options);
		});

		$container->add(FileStorageInterface::class, static function () use ($container): FileStorageInterface {
			$client = $container->get(S3Client::class);
			assert($client instanceof S3Client);
			$config = $container->get(S3Config::class);
			assert($config instanceof S3Config);
			$logger = $container->get(LoggerInterface::class);
			assert($logger instanceof LoggerInterface);
			return new S3FileStorage($client, $config, $logger);
		});

		$container->add(
			ClientInterface::class,
			static fn (): ClientInterface => new Client('tcp://' . getenv('REDIS_HOST') . ':' . getenv('REDIS_PORT'), [
				'parameters' => [
					'password' => (string) getenv('REDIS_PASSWORD'),
				],
			]),
		);

		$container->add(CacheFactoryInterface::class, static function () use ($container): CacheFactoryInterface {
			$redisClient = $container->get(ClientInterface::class);
			assert($redisClient instanceof ClientInterface);
			return new CacheFactory($redisClient);
		});

		$container->add(
			CorsPolicy::class,
			static fn (): CorsPolicy => CorsPolicy::fromEnvValue((string) getenv('BACKEND_CORS_ALLOWED_ORIGIN')),
		);

		$container->add(QueuePublisher::class, static fn (): QueuePublisher => new QueuePublisher());

		$container->add(TabServiceClientInterface::class, static fn (): TabServiceClientInterface => new TabServiceClient());
	}
}
