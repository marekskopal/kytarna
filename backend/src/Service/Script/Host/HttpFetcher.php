<?php

declare(strict_types=1);

namespace Ukolio\Service\Script\Host;

use CurlHandle;
use RuntimeException;
use const CURLINFO_RESPONSE_CODE;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_HEADERFUNCTION;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_PROTOCOLS;
use const CURLOPT_RESOLVE;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT_MS;
use const CURLOPT_URL;
use const CURLPROTO_HTTP;
use const CURLPROTO_HTTPS;
use const DNS_AAAA;
use const FILTER_FLAG_NO_PRIV_RANGE;
use const FILTER_FLAG_NO_RES_RANGE;
use const FILTER_VALIDATE_IP;
use const PHP_URL_HOST;
use const PHP_URL_PORT;
use const PHP_URL_SCHEME;

/**
 * Outbound HTTP for `ukolio.fetch`. Stateless; per-run caps are enforced by the caller via
 * ScriptRunContext before each call. Only http/https are permitted and the timeout is bounded.
 *
 * SSRF defenses (always on, independent of the optional per-workspace allowlist): the target host
 * is resolved up front and rejected if any resolved address is private, loopback, link-local
 * (incl. cloud metadata 169.254.169.254) or otherwise reserved; the validated IPs are then pinned
 * with CURLOPT_RESOLVE so cURL cannot re-resolve to a different address (DNS rebinding). Redirects
 * are disabled and the cURL protocol set is restricted to http/https.
 */
final readonly class HttpFetcher
{
	private const int DefaultTimeoutMs = 10000;
	private const int MaxTimeoutMs = 30000;
	private const int MaxBodyBytes = 5242880;

	/**
	 * @param array<string, mixed> $options
	 * @param list<string> $allowedHosts lowercase host patterns; empty means no restriction
	 */
	public function fetch(string $url, array $options, array $allowedHosts = []): HttpResponse
	{
		if (preg_match('#^https?://#i', $url) !== 1) {
			throw new RuntimeException('ukolio.fetch only supports http(s) URLs.');
		}

		$this->assertHostAllowed($url, $allowedHosts);
		$target = $this->resolveSafeTarget($url);

		$method = strtoupper(JsValue::string($options['method'] ?? null) ?? 'GET');
		$timeoutMs = min(max(JsValue::int($options['timeoutMs'] ?? null) ?? self::DefaultTimeoutMs, 1), self::MaxTimeoutMs);

		$handle = curl_init();
		if ($handle === false) {
			throw new RuntimeException('ukolio.fetch could not initialise an HTTP client.');
		}

		$responseHeaders = [];
		curl_setopt_array($handle, [
			CURLOPT_URL => $url,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
			// Pin the validated addresses so cURL cannot re-resolve the host to an internal IP.
			CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $target['host'], $target['port'], implode(',', $target['ips']))],
			CURLOPT_TIMEOUT_MS => $timeoutMs,
			CURLOPT_HTTPHEADER => $this->buildHeaders(JsValue::toAssoc($options['headers'] ?? null)),
			CURLOPT_HEADERFUNCTION => static function (CurlHandle $_, string $header) use (&$responseHeaders): int {
				$parts = explode(':', $header, 2);
				if (count($parts) === 2) {
					$responseHeaders[trim($parts[0])] = trim($parts[1]);
				}

				return strlen($header);
			},
		]);

		$body = JsValue::string($options['body'] ?? null);
		if ($body !== null && $method !== 'GET' && $method !== 'HEAD') {
			curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
		}

		$result = curl_exec($handle);
		if ($result === false) {
			throw new RuntimeException('ukolio.fetch failed: ' . curl_error($handle));
		}

		$status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

		$text = is_string($result) ? $result : '';
		if (strlen($text) > self::MaxBodyBytes) {
			throw new RuntimeException('ukolio.fetch response exceeded the maximum allowed size.');
		}

		return new HttpResponse($status, $responseHeaders, $text);
	}

	/**
	 * Enforce the optional per-workspace outbound-fetch allowlist. A pattern matches the URL host
	 * exactly, or as a parent domain (`example.com` allows `api.example.com`).
	 *
	 * @param list<string> $allowedHosts
	 */
	private function assertHostAllowed(string $url, array $allowedHosts): void
	{
		if ($allowedHosts === []) {
			return;
		}

		$host = strtolower((string) parse_url($url, PHP_URL_HOST));
		if ($host === '') {
			throw new RuntimeException('ukolio.fetch could not determine the request host.');
		}

		foreach ($allowedHosts as $pattern) {
			if ($host === $pattern || str_ends_with($host, '.' . $pattern)) {
				return;
			}
		}

		throw new RuntimeException(sprintf('ukolio.fetch host "%s" is not in this workspace\'s allowlist.', $host));
	}

	/**
	 * Resolve the URL host and reject it if any address is non-public (SSRF guard). Returns the host,
	 * port, and the validated IPs to pin via CURLOPT_RESOLVE.
	 *
	 * @return array{host: string, port: int, ips: list<string>}
	 */
	private function resolveSafeTarget(string $url): array
	{
		$host = strtolower((string) parse_url($url, PHP_URL_HOST));
		if ($host === '') {
			throw new RuntimeException('ukolio.fetch could not determine the request host.');
		}

		$scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
		$port = parse_url($url, PHP_URL_PORT);
		$port = is_int($port) ? $port : ($scheme === 'https' ? 443 : 80);

		$ips = $this->resolveIps($host);
		foreach ($ips as $ip) {
			if (!self::isPublicIp($ip)) {
				throw new RuntimeException('ukolio.fetch refuses to connect to a non-public address.');
			}
		}

		return ['host' => $host, 'port' => $port, 'ips' => $ips];
	}

	/**
	 * IPv4 + IPv6 addresses for a host. A literal IP resolves to itself (no DNS lookup).
	 *
	 * @return list<string>
	 */
	private function resolveIps(string $host): array
	{
		$literal = trim($host, '[]');
		if (filter_var($literal, FILTER_VALIDATE_IP) !== false) {
			return [$literal];
		}

		$resolved = gethostbynamel($host);
		$ips = $resolved === false ? [] : $resolved;
		$aaaa = @dns_get_record($host, DNS_AAAA);
		if (is_array($aaaa)) {
			foreach ($aaaa as $record) {
				if (isset($record['ipv6']) && is_string($record['ipv6'])) {
					$ips[] = $record['ipv6'];
				}
			}
		}

		$ips = array_values(array_unique(array_filter($ips, 'is_string')));
		if ($ips === []) {
			throw new RuntimeException('ukolio.fetch could not resolve the request host.');
		}

		return $ips;
	}

	/** Reject private (RFC 1918 / ULA), loopback, link-local (incl. 169.254.169.254) and reserved ranges. */
	private static function isPublicIp(string $ip): bool
	{
		return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
	}

	/**
	 * @param array<string, mixed> $headers
	 * @return list<string>
	 */
	private function buildHeaders(array $headers): array
	{
		$out = [];
		foreach ($headers as $name => $value) {
			$stringValue = JsValue::string($value);
			if ($stringValue !== null) {
				$out[] = $name . ': ' . $stringValue;
			}
		}

		return $out;
	}
}
