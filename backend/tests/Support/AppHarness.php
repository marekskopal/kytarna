<?php

declare(strict_types=1);

namespace Ukolio\Tests\Support;

use MarekSkopal\ORM\ORM;
use PDO;
use Psr\Container\ContainerInterface;
use Ukolio\App\Application;
use Ukolio\App\ApplicationFactory;
use Ukolio\Service\Actor\ActorContextInterface;

/**
 * Shared per-suite singleton: builds the Application once, exposes the
 * container for tests, and resets database + identity map between tests.
 */
final class AppHarness
{
	private static ?Application $application = null;

	/** @var list<string>|null */
	private static ?array $tables = null;

	public static function initialize(): void
	{
		if (self::$application !== null) {
			return;
		}
		self::$application = ApplicationFactory::create();
	}

	public static function app(): Application
	{
		if (self::$application === null) {
			throw new \LogicException('AppHarness not initialized; tests bootstrap missed.');
		}
		return self::$application;
	}

	public static function container(): ContainerInterface
	{
		return self::app()->container;
	}

	public static function pdo(): PDO
	{
		return self::app()->dbContext->getDatabase()->getPdo();
	}

	/**
	 * Wipe all tables (except migrations) and clear the ORM identity map so
	 * the next test starts from a clean slate.
	 */
	public static function resetState(): void
	{
		$pdo = self::pdo();

		if (self::$tables === null) {
			$stmt = $pdo->query('SHOW TABLES');
			if ($stmt === false) {
				throw new \RuntimeException('SHOW TABLES query failed');
			}
			$tables = [];
			foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $row) {
				if (!is_string($row) || $row === 'migrations') {
					continue;
				}
				$tables[] = $row;
			}
			self::$tables = $tables;
		}

		$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
		foreach (self::$tables as $table) {
			$pdo->exec('TRUNCATE TABLE `' . $table . '`');
		}
		$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

		$orm = self::container()->get(ORM::class);
		assert($orm instanceof ORM);
		$orm->getEntityCache()->clear();

		// The shared container reuses mutable, per-request contexts; production resets these at the
		// start of every request (frankenphp-worker.php). Mirror that so an Agent actor set by an
		// MCP test doesn't leak into a later HTTP test (which would mis-tag events as agent-driven).
		$actorContext = self::container()->get(ActorContextInterface::class);
		assert($actorContext instanceof ActorContextInterface);
		$actorContext->setHuman();
	}
}
