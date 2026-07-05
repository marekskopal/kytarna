<?php

declare(strict_types=1);

namespace Kytario\Tests\Service\Realtime;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Update;

/**
 * In-process HubInterface for tests — records every publish() call so we can
 * assert that mutations broadcast to the right topic with the right payload.
 */
final class RecordingMercureHub implements HubInterface
{
	/** @var list<Update> */
	public array $updates = [];

	public function publish(Update $update): string
	{
		$this->updates[] = $update;
		return 'urn:uuid:fake-' . count($this->updates);
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
