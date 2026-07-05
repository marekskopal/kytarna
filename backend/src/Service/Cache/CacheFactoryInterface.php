<?php

declare(strict_types=1);

namespace Ukolio\Service\Cache;

interface CacheFactoryInterface
{
	public function create(CacheStorageEnum $driver = CacheStorageEnum::Memcached, ?string $namespace = null): Cache;
}
