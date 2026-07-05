<?php

declare(strict_types=1);

namespace Kytario\OAuth;

use Kytario\Route\Routes;
use Psr\Http\Message\ServerRequestInterface;
use const PHP_URL_HOST;

/**
 * Builds the base URL advertised in the OAuth discovery documents and validates
 * RFC 8707 resource indicators against it.
 */
final readonly class DiscoveryUrl
{
	public static function baseUrl(ServerRequestInterface $request): string
	{
		$scheme = $request->getHeaderLine('X-Forwarded-Proto');
		if ($scheme === '') {
			$scheme = $request->getUri()->getScheme();
		}

		$host = $request->getHeaderLine('X-Forwarded-Host');
		if ($host === '') {
			$host = $request->getHeaderLine('Host');
		}
		if ($host === '') {
			$host = $request->getUri()->getAuthority();
		}

		// MCP clients follow the discovery documents blindly, so the advertised
		// issuer/endpoint URLs must not be attacker-influencable. The request host is
		// honored (with its port) only when its hostname matches the configured
		// PROXY_HOST; anything else gets the canonical configured base URL.
		$configuredHost = (string) getenv('PROXY_HOST');
		if ($configuredHost !== '' && !self::hostnameMatches($host, $configuredHost)) {
			$portSsl = (string) getenv('PROXY_PORT_SSL');
			$portSuffix = $portSsl === '' || $portSsl === '443' ? '' : ':' . $portSsl;

			return 'https://' . $configuredHost . $portSuffix;
		}

		return $scheme . '://' . $host;
	}

	/**
	 * RFC 8707 resource indicator validation. This authorization server protects exactly
	 * one resource — its own MCP endpoint — and issues opaque tokens validated against its
	 * own store. Rejecting a `resource` that names anything else prevents a client from
	 * being steered into minting a token here for a different (e.g. attacker) resource,
	 * closing the OAuth confused-deputy vector. The indicator is optional (absent → allowed)
	 * to stay compatible with clients that predate resource indicators.
	 */
	public static function resourceMatches(ServerRequestInterface $request, mixed $resource): bool
	{
		if (!is_string($resource) || $resource === '') {
			return true;
		}

		$canonical = self::baseUrl($request) . Routes::Mcp->value;

		return rtrim($resource, '/') === rtrim($canonical, '/');
	}

	private static function hostnameMatches(string $hostWithOptionalPort, string $configuredHost): bool
	{
		$hostname = parse_url('//' . $hostWithOptionalPort, PHP_URL_HOST);
		if (!is_string($hostname) || $hostname === '') {
			return false;
		}

		return strtolower($hostname) === strtolower($configuredHost);
	}
}
