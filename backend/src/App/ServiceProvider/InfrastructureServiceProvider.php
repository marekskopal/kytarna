<?php

declare(strict_types=1);

namespace Ukolio\App\ServiceProvider;

use AsyncAws\S3\S3Client;
use Http\Discovery\Psr17FactoryDiscovery;
use League\Container\ServiceProvider\AbstractServiceProvider;
use Predis\Client;
use Predis\ClientInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Ukolio\Model\Repository\TaskCommentRepository;
use Ukolio\Model\Repository\TaskFieldValueRepository;
use Ukolio\Model\Repository\TaskRepository;
use Ukolio\Model\Repository\TaskTagRepository;
use Ukolio\Service\Cache\CacheFactory;
use Ukolio\Service\Cache\CacheFactoryInterface;
use Ukolio\Service\Cors\CorsPolicy;
use Ukolio\Service\Logger\Logger;
use Ukolio\Service\Queue\QueuePublisher;
use Ukolio\Service\Search\MeiliClient;
use Ukolio\Service\Search\SearchIndexer;
use Ukolio\Service\Search\TaskDocumentBuilder;
use Ukolio\Service\Storage\FileStorageInterface;
use Ukolio\Service\Storage\S3Config;
use Ukolio\Service\Storage\S3FileStorage;

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
			TaskDocumentBuilder::class,
			MeiliClient::class,
			SearchIndexer::class,
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

		$container->add(TaskDocumentBuilder::class, static function () use ($container): TaskDocumentBuilder {
			$comments = $container->get(TaskCommentRepository::class);
			assert($comments instanceof TaskCommentRepository);
			$fieldValues = $container->get(TaskFieldValueRepository::class);
			assert($fieldValues instanceof TaskFieldValueRepository);
			$taskTags = $container->get(TaskTagRepository::class);
			assert($taskTags instanceof TaskTagRepository);
			return new TaskDocumentBuilder($comments, $fieldValues, $taskTags);
		});

		$container->add(MeiliClient::class, static function () use ($container): MeiliClient {
			$builder = $container->get(TaskDocumentBuilder::class);
			assert($builder instanceof TaskDocumentBuilder);
			$tasks = $container->get(TaskRepository::class);
			assert($tasks instanceof TaskRepository);
			$logger = $container->get(LoggerInterface::class);
			assert($logger instanceof LoggerInterface);
			return new MeiliClient($builder, $tasks, $logger);
		});

		$container->add(SearchIndexer::class, static function () use ($container): SearchIndexer {
			$publisher = $container->get(QueuePublisher::class);
			assert($publisher instanceof QueuePublisher);
			$logger = $container->get(LoggerInterface::class);
			assert($logger instanceof LoggerInterface);
			return new SearchIndexer($publisher, $logger);
		});
	}
}
