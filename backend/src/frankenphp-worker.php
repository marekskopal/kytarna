<?php

declare(strict_types=1);

namespace Ukolio;

require_once __DIR__ . '/../vendor/autoload.php';

use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Psr\Log\LoggerInterface;
use Ukolio\App\ApplicationFactory;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Response\ErrorResponse;
use Ukolio\Service\Actor\ActorContextInterface;
use Ukolio\Service\Realtime\RealtimeOriginContextInterface;

$application = ApplicationFactory::create();

$logger = $application->container->get(LoggerInterface::class);
assert($logger instanceof LoggerInterface);

$mcpUserContext = $application->container->get(McpUserContextInterface::class);
assert($mcpUserContext instanceof McpUserContextInterface);

$actorContext = $application->container->get(ActorContextInterface::class);
assert($actorContext instanceof ActorContextInterface);

$realtimeOriginContext = $application->container->get(RealtimeOriginContextInterface::class);
assert($realtimeOriginContext instanceof RealtimeOriginContextInterface);

$emitter = new SapiEmitter();

$handler = static function () use ($application, $logger, $emitter, $mcpUserContext, $actorContext, $realtimeOriginContext): void {
	// Per-request reset of mutable, container-shared contexts.
	$mcpUserContext->clear();
	$actorContext->setHuman();
	$realtimeOriginContext->clear();

	try {
		$request = ServerRequestFactory::fromGlobals();
		$response = $application->handler->handle($request);
		$emitter->emit($response);
	} catch (\Throwable $e) {
		$logger->error($e->getMessage(), ['exception' => $e]);
		$emitter->emit(ErrorResponse::fromException($e));
	}
};

while (frankenphp_handle_request($handler)) {
	$application->dbContext->getOrm()->getEntityCache()->clear();
	gc_collect_cycles();
}
