<?php

declare(strict_types=1);

namespace Ukolio\App\ServiceProvider;

use League\Container\ServiceProvider\AbstractServiceProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\HubInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Realtime\MercureCookieIssuer;
use Ukolio\Service\Realtime\MercureCookieIssuerInterface;
use Ukolio\Service\Realtime\MercurePublisherTokenProvider;
use Ukolio\Service\Realtime\NullMercureHub;
use Ukolio\Service\Realtime\RealtimeOriginContext;
use Ukolio\Service\Realtime\RealtimeOriginContextInterface;
use Ukolio\Service\Realtime\RealtimePublisher;
use Ukolio\Service\Realtime\RealtimePublisherInterface;

final class RealtimeServiceProvider extends AbstractServiceProvider
{
	public function provides(string $id): bool
	{
		return in_array($id, [
			HubInterface::class,
			RealtimePublisherInterface::class,
			RealtimeOriginContextInterface::class,
			MercureCookieIssuerInterface::class,
		], true);
	}

	public function register(): void
	{
		$container = $this->getContainer();

		$container->add(RealtimeOriginContextInterface::class, RealtimeOriginContext::class);

		$container->add(HubInterface::class, static function (): HubInterface {
			$url = (string) getenv('MERCURE_PUBLISH_URL');
			$key = (string) getenv('MERCURE_PUBLISHER_JWT_KEY');
			if ($url === '' || $key === '') {
				return new NullMercureHub();
			}
			return new Hub($url, new MercurePublisherTokenProvider($key));
		});

		$container->add(RealtimePublisherInterface::class, static function () use ($container): RealtimePublisherInterface {
			$hub = $container->get(HubInterface::class);
			assert($hub instanceof HubInterface);
			$origin = $container->get(RealtimeOriginContextInterface::class);
			assert($origin instanceof RealtimeOriginContextInterface);
			$logger = $container->get(LoggerInterface::class);
			assert($logger instanceof LoggerInterface);
			return new RealtimePublisher($hub, $origin, $logger);
		});

		$container->add(MercureCookieIssuerInterface::class, static function () use ($container): MercureCookieIssuerInterface {
			$workspaceProvider = $container->get(WorkspaceProviderInterface::class);
			assert($workspaceProvider instanceof WorkspaceProviderInterface);
			return new MercureCookieIssuer($workspaceProvider, (string) getenv('MERCURE_SUBSCRIBER_JWT_KEY'));
		});
	}
}
