<?php

declare(strict_types=1);

namespace Ukolio\Service\Realtime;

use Firebase\JWT\JWT;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;

/**
 * Builds a short-lived publisher JWT on each Hub publish call.
 *
 * The Mercure publisher JWT is signed with MERCURE_PUBLISHER_JWT_KEY and carries
 * a `mercure.publish` claim of ["*"] so the backend may publish to every topic
 * it emits (workspace-scoped topics, see RealtimePublisher::TopicPrefix).
 */
final readonly class MercurePublisherTokenProvider implements TokenProviderInterface
{
	private const string Algorithm = 'HS256';
	private const int TtlSeconds = 60;

	public function __construct(private string $key)
	{
	}

	public function getJwt(): string
	{
		return JWT::encode(
			[
				'mercure' => ['publish' => ['*']],
				'exp' => time() + self::TtlSeconds,
			],
			$this->key,
			self::Algorithm,
		);
	}
}
