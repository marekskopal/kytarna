<?php

declare(strict_types=1);

namespace Ukolio\Service\Cache;

use Contributte\Redis\Caching\RedisJournal;
use Contributte\Redis\Caching\RedisStorage;
use Contributte\Redis\Serializer\IgbinarySerializer;
use Nette\Bridges\Psr\PsrCacheAdapter;
use Nette\Caching\Storages\MemcachedStorage;
use Predis\ClientInterface;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;

final class CacheFactory implements CacheFactoryInterface
{
	/** @var array<string, array<string, Cache>> */
	private array $caches = [];

	public function __construct(private readonly ClientInterface $redisClient)
	{
	}

	public function create(CacheStorageEnum $driver = CacheStorageEnum::Memcached, ?string $namespace = null): Cache
	{
		$namespaceKey = $namespace ?? '';

		if (isset($this->caches[$driver->value][$namespaceKey])) {
			return $this->caches[$driver->value][$namespaceKey];
		}

		$this->caches[$driver->value][$namespaceKey] = match ($driver) {
			CacheStorageEnum::Memcached => self::createMemcachedCache($namespaceKey),
			CacheStorageEnum::Redis => self::createRedisCache($this->redisClient, $namespaceKey),
		};

		return $this->caches[$driver->value][$namespaceKey];
	}

	public static function createPsrCache(CacheStorageEnum $driver = CacheStorageEnum::Memcached, ?string $namespace = null): CacheInterface
	{
		$namespaceKey = $namespace ?? '';

		$cache = match ($driver) {
			CacheStorageEnum::Memcached => self::createMemcachedCache($namespaceKey),
			CacheStorageEnum::Redis => throw new RuntimeException(
				'Static Redis PSR cache is not supported; resolve CacheFactory from the container.',
			),
		};
		return new PsrCacheAdapter($cache->getStorage());
	}

	private static function createMemcachedCache(string $namespace): Cache
	{
		$storage = new MemcachedStorage(
			host: (string) getenv('MEMCACHED_HOST'),
			port: (int) getenv('MEMCACHED_PORT'),
		);
		return new Cache($storage, $namespace);
	}

	private static function createRedisCache(ClientInterface $redisClient, string $namespace): Cache
	{
		$storage = new RedisStorage($redisClient, new RedisJournal($redisClient), new IgbinarySerializer());
		return new Cache($storage, $namespace);
	}
}
