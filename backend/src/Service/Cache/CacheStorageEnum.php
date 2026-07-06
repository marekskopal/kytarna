<?php

declare(strict_types=1);

namespace Kytarna\Service\Cache;

enum CacheStorageEnum: string
{
	case Memcached = 'memcached';
	case Redis = 'redis';
}
