<?php

declare(strict_types=1);

namespace Kytario;

require_once __DIR__ . '/../vendor/autoload.php';

use Kytario\App\ApplicationFactory;
use Kytario\Mcp\McpUserContextInterface;
use Kytario\Response\ErrorResponse;
use Kytario\Service\Actor\ActorContextInterface;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Psr\Log\LoggerInterface;

$application = ApplicationFactory::create();

$logger = $application->container->get(LoggerInterface::class);
assert($logger instanceof LoggerInterface);

$mcpUserContext = $application->container->get(McpUserContextInterface::class);
assert($mcpUserContext instanceof McpUserContextInterface);

$actorContext = $application->container->get(ActorContextInterface::class);
assert($actorContext instanceof ActorContextInterface);

$emitter = new SapiEmitter();

$handler = static function () use ($application, $logger, $emitter, $mcpUserContext, $actorContext): void {
	// Per-request reset of mutable, container-shared contexts.
	$mcpUserContext->clear();
	$actorContext->setHuman();

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
