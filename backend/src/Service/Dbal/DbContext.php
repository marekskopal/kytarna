<?php

declare(strict_types=1);

namespace Ukolio\Service\Dbal;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Migrations\Migrator;
use MarekSkopal\ORM\ORM;
use MarekSkopal\ORM\Schema\Builder\SchemaBuilder;
use MarekSkopal\ORM\Schema\Schema;
use Ukolio\Service\Cache\CacheFactory;

final readonly class DbContext
{
	private const string CacheNamespace = 'Orm';
	private const string CacheKey = 'Schema';

	private ReconnectableDatabase $database;

	private Schema $schema;

	private ORM $orm;

	public function __construct(string $host, string $name, string $user, string $password)
	{
		$this->database = new ReconnectableDatabase($host, $name, $user, $password);

		$cache = CacheFactory::createPsrCache(namespace: self::CacheNamespace);
		$schema = $cache->get(self::CacheKey);
		if ($schema instanceof Schema) {
			$this->schema = $schema;
			$this->orm = new ORM($this->database, $schema);
			return;
		}

		$this->schema = new SchemaBuilder()
			->addEntityPath(__DIR__ . '/../../Model/Entity')
			->build();

		$cache->set(self::CacheKey, $this->schema);

		$this->orm = new ORM($this->database, $this->schema);
	}

	public function getOrm(): ORM
	{
		return $this->orm;
	}

	public function getDatabase(): DatabaseInterface
	{
		return $this->database;
	}

	public function getMigrator(): Migrator
	{
		return new Migrator(__DIR__ . '/../../../migrations/', $this->database->getInnerDatabase());
	}

	public function getSchema(): Schema
	{
		return $this->schema;
	}

	/** @api */
	public function clearCache(): void
	{
		$cache = CacheFactory::createPsrCache(namespace: self::CacheNamespace);
		$cache->clear();
	}
}
