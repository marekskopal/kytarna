<?php

declare(strict_types=1);

namespace Kytario\Service\Realtime;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Update;

/**
 * Used when MERCURE_PUBLISH_URL / MERCURE_PUBLISHER_JWT_KEY are not configured
 * (e.g. inside the test suite). publish() is a no-op so mutations stay green
 * without spamming logs with hub-unreachable warnings.
 */
final readonly class NullMercureHub implements HubInterface
{
	public function publish(Update $update): string
	{
		return '';
	}

	public function getPublicUrl(): string
	{
		return '';
	}

	public function getFactory(): ?TokenFactoryInterface
	{
		return null;
	}
}
