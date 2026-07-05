<?php

declare(strict_types=1);

use Ukolio\Tests\Support\AppHarness;

require __DIR__ . '/../vendor/autoload.php';

// Force deterministic env for the test suite. putenv() takes precedence over
// container-provided env vars when ApplicationFactory reads getenv().
$testEnv = [
	'AUTHORIZATION_TOKEN_KEY' => 'test-token-key-test-token-key-test-token-key-0123',
	'MYSQL_HOST' => getenv('MYSQL_HOST') !== false && getenv('MYSQL_HOST') !== '' ? getenv('MYSQL_HOST') : 'db',
	'MYSQL_USER' => getenv('MYSQL_USER') !== false && getenv('MYSQL_USER') !== '' ? getenv('MYSQL_USER') : 'ukolio',
	'MYSQL_PASSWORD' => getenv('MYSQL_PASSWORD') !== false && getenv('MYSQL_PASSWORD') !== '' ? getenv('MYSQL_PASSWORD') : 'ukolio',
	'MYSQL_DATABASE' => getenv('TEST_MYSQL_DATABASE') !== false && getenv('TEST_MYSQL_DATABASE') !== ''
		? (string) getenv('TEST_MYSQL_DATABASE')
		: 'ukolio_test',
	'S3_BUCKET' => 'test-bucket',
	'S3_ACCESS_KEY' => 'test-access-key',
	'S3_SECRET_KEY' => 'test-secret-key',
	'S3_ENDPOINT' => 'http://localhost:9000',
	'S3_REGION' => 'us-east-1',
	'S3_USE_PATH_STYLE' => 'true',
	'REDIS_HOST' => getenv('REDIS_HOST') !== false && getenv('REDIS_HOST') !== '' ? getenv('REDIS_HOST') : 'redis',
	'REDIS_PORT' => getenv('REDIS_PORT') !== false && getenv('REDIS_PORT') !== '' ? getenv('REDIS_PORT') : '6379',
	'REDIS_PASSWORD' => getenv('REDIS_PASSWORD') !== false && getenv('REDIS_PASSWORD') !== '' ? getenv('REDIS_PASSWORD') : 'ukolio',
	'RABBITMQ_HOST' => getenv('RABBITMQ_HOST') !== false && getenv('RABBITMQ_HOST') !== '' ? getenv('RABBITMQ_HOST') : 'rabbitmq',
	'RABBITMQ_PORT' => getenv('RABBITMQ_PORT') !== false && getenv('RABBITMQ_PORT') !== '' ? getenv('RABBITMQ_PORT') : '5672',
	'RABBITMQ_USER' => getenv('RABBITMQ_USER') !== false && getenv('RABBITMQ_USER') !== '' ? getenv('RABBITMQ_USER') : 'ukolio',
	'RABBITMQ_PASSWORD' => getenv('RABBITMQ_PASSWORD') !== false && getenv('RABBITMQ_PASSWORD') !== '' ? getenv(
		'RABBITMQ_PASSWORD',
	) : 'ukolio',
	'BACKEND_AMQP_CONSUMER_PREFETCH' => getenv('BACKEND_AMQP_CONSUMER_PREFETCH') !== false && getenv(
		'BACKEND_AMQP_CONSUMER_PREFETCH',
	) !== '' ? getenv('BACKEND_AMQP_CONSUMER_PREFETCH') : '10',
	'SMTP_HOST' => 'localhost',
	'SMTP_PORT' => '2525',
	'SMTP_USER' => '',
	'SMTP_PASSWORD' => '',
	'EMAIL_FROM' => 'test@example.com',
	'PROXY_HOST' => 'test.local',
	'PROXY_PORT_SSL' => '443',
	'TASK_FILE_MAX_SIZE_MB' => '1',
	'MCP_SESSION_TTL' => '60',
	'BACKEND_CORS_ALLOWED_ORIGIN' => '*',
	'BACKEND_LOG_LEVEL' => 'production',
	'MERCURE_PUBLISHER_JWT_KEY' => 'test-mercure-publisher-key-0000000000000000',
	'MERCURE_SUBSCRIBER_JWT_KEY' => 'test-mercure-subscriber-key-0000000000000000',
	'MERCURE_PUBLISH_URL' => '',
];
foreach ($testEnv as $name => $value) {
	putenv($name . '=' . $value);
	$_ENV[$name] = $value;
	$_SERVER[$name] = $value;
}

$host = (string) getenv('MYSQL_HOST');
$database = (string) getenv('MYSQL_DATABASE');
$user = (string) getenv('MYSQL_USER');
$password = (string) getenv('MYSQL_PASSWORD');

if ($database === 'ukolio') {
	fwrite(
		STDERR,
		"Refusing to run tests against the production database 'ukolio'. "
		. "Set TEST_MYSQL_DATABASE to a dedicated test database (default: ukolio_test).\n",
	);
	exit(1);
}

try {
	$adminPdo = new PDO(
		'mysql:host=' . $host . ';charset=utf8mb4',
		$user,
		$password,
		[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
	);
	$adminPdo->exec('CREATE DATABASE IF NOT EXISTS `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
} catch (PDOException $e) {
	fwrite(
		STDERR,
		"Cannot create test database '" . $database . "' on host '" . $host . "' as user '" . $user . "': " . $e->getMessage() . "\n"
		. "Hint: grant the user CREATE privilege, or pre-create the database manually.\n",
	);
	exit(1);
}

$pdo = new PDO(
	'mysql:host=' . $host . ';dbname=' . $database . ';charset=utf8mb4',
	$user,
	$password,
	[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$showTables = $pdo->query('SHOW TABLES');
if ($showTables === false) {
	fwrite(STDERR, "SHOW TABLES query failed.\n");
	exit(1);
}
foreach ($showTables->fetchAll(PDO::FETCH_COLUMN) as $table) {
	if (!is_string($table)) {
		continue;
	}
	$pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

AppHarness::initialize();
AppHarness::app()->dbContext->getMigrator()->migrate();
