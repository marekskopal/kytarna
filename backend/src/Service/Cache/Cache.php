<?php

declare(strict_types=1);

namespace Ukolio\Service\Cache;

use Nette\Caching\Storage;

final readonly class Cache
{
	private \Nette\Caching\Cache $cache;

	public function __construct(private Storage $storage, string $namespace)
	{
		$this->cache = new \Nette\Caching\Cache($storage, $namespace);
	}

	public function save(string $key, mixed $data, ?int $expireSeconds = null): mixed
	{
		$dependencies = null;
		if ($expireSeconds !== null) {
			$dependencies = [\Nette\Caching\Cache::Expire => $expireSeconds];
		}

		return $this->cache->save($key, $data, $dependencies);
	}

	public function load(string $key): mixed
	{
		return $this->cache->load($key);
	}

	public function remove(string $key): void
	{
		$this->cache->remove($key);
	}

	public function cleanAll(): void
	{
		$this->cache->clean([\Nette\Caching\Cache::All => true]);
	}

	public function getStorage(): Storage
	{
		return $this->storage;
	}
}
