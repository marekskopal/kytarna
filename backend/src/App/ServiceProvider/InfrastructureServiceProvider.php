<?php

declare(strict_types=1);

namespace Kytario\App\ServiceProvider;

use AsyncAws\S3\S3Client;
use Http\Discovery\Psr17FactoryDiscovery;
use League\Container\ServiceProvider\AbstractServiceProvider;
use Predis\Client;
use Predis\ClientInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Kytario\Model\Repository\TaskCommentRepository;
use Kytario\Model\Repository\TaskFieldValueRepository;
use Kytario\Model\Repository\TaskRepository;
use Kytario\Model\Repository\TaskTagRepository;
use Kytario\Service\Cache\CacheFactory;
use Kytario\Service\Cache\CacheFactoryInterface;
use Kytario\Service\Cors\CorsPolicy;
use Kytario\Service\Logger\Logger;
use Kytario\Service\Queue\QueuePublisher;
use Kytario\Service\Search\MeiliClient;
use Kytario\Service\Search\SearchIndexer;
use Kytario\Service\Search\TaskDocumentBuilder;
use Kytario\Service\Storage\FileStorageInterface;
use Kytario\Service\Storage\S3Config;
use Kytario\Service\Storage\S3FileStorage;

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
