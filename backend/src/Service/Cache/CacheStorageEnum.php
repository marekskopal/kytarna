<?php

declare(strict_types=1);

namespace Ukolio\Service\Cache;

enum CacheStorageEnum: string
{
	case Memcached = 'memcached';
	case Redis = 'redis';
}
