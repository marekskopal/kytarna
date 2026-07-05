<?php

declare(strict_types=1);

namespace Ukolio\Middleware;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use stdClass;
use Ukolio\Middleware\Exception\NotAuthorizedException;
use Ukolio\Route\Routes;
use Ukolio\Service\Authentication\AuthenticationServiceInterface;
use Ukolio\Service\Provider\UserProviderInterface;
use Ukolio\Service\Realtime\RealtimeOriginContextInterface;

final readonly class AuthorizationMiddleware implements MiddlewareInterface
{
	public const string AttributeUser = 'user';

	private const string AttributeToken = 'token';
	private const string AuthHeader = 'Authorization';
	private const string AuthHeaderType = 'Bearer ';
	private const string OriginClientIdHeader = 'X-Origin-Client-Id';

	private const array OpenRoutes = [
		Routes::Health->value,
		Routes::AuthenticationLogin->value,
		Routes::AuthenticationLogout->value,
		Routes::AuthenticationSignUp->value,
		Routes::AuthenticationRequestPasswordReset->value,
		Routes::AuthenticationConfirmPasswordReset->value,
		Routes::AuthenticationVerifyEmail->value,
		Routes::AuthenticationGoogleClientId->value,
		Routes::AuthenticationGoogleLogin->value,
		Routes::InvitationLookup->value,
		Routes::Mcp->value,
		Routes::OAuthMetadata->value,
		Routes::OAuthResourceMetadata->value,
		Routes::OAuthToken->value,
		Routes::OAuthRegister->value,
		Routes::OAuthClientInfo->value,
	];

	public function __construct(private UserProviderInterface $userProvider, private RealtimeOriginContextInterface $realtimeOriginContext,)
	{
	}

	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		$this->captureOriginClientId($request);

		if (in_array($request->getUri()->getPath(), self::OpenRoutes, strict: true)) {
			return $handler->handle($request);
		}

		if ($request->getMethod() === 'OPTIONS') {
			return $handler->handle($request);
		}

		$jwtToken = $this->extractToken($request);

		try {
			/** @var object{id: int, tv?: int}&stdClass $token */
			$token = JWT::decode(
				$jwtToken,
				new Key((string) getenv('AUTHORIZATION_TOKEN_KEY'), AuthenticationServiceInterface::TokenAlgorithm),
			);
		} catch (ExpiredException $exception) {
			return $this->handleExpiredToken($request, $handler, $exception);
		} catch (\Throwable $exception) {
			throw new NotAuthorizedException('AccessToken is invalid.', $request, 401, $exception);
		}

		$this->assertAccessType($token, $request);

		$request = $this->withUserAttribute($request, $token->id, $this->tokenVersion($token));
		$request = $request->withAttribute(self::AttributeToken, $jwtToken);

		return $handler->handle($request);
	}

	/**
	 * A refresh token must not double as an access token (it would stretch a stolen
	 * token's life from 1 h to 7 d). Tokens minted before the `type` claim existed
	 * carry none and stay valid until they expire naturally.
	 */
	private function assertAccessType(object $token, ServerRequestInterface $request): void
	{
		if (isset($token->type) && $token->type !== AuthenticationServiceInterface::TokenTypeAccess) {
			throw new NotAuthorizedException('AccessToken is invalid.', $request);
		}
	}

	private function captureOriginClientId(ServerRequestInterface $request): void
	{
		$header = $request->getHeader(self::OriginClientIdHeader)[0] ?? null;
		$this->realtimeOriginContext->set($header);
	}

	private function extractToken(ServerRequestInterface $request): string
	{
		$authorizationHeader = $request->getHeader(self::AuthHeader)[0] ?? null;

		if ($authorizationHeader === null) {
			throw new NotAuthorizedException('Authorization header not found', $request);
		}

		if (!str_starts_with($authorizationHeader, self::AuthHeaderType)) {
			throw new NotAuthorizedException('Authorization header is not Bearer type', $request);
		}

		return substr($authorizationHeader, strlen(self::AuthHeaderType));
	}

	private function handleExpiredToken(
		ServerRequestInterface $request,
		RequestHandlerInterface $handler,
		ExpiredException $exception,
	): ResponseInterface {
		if ($request->getUri()->getPath() !== Routes::AuthenticationRefreshToken->value) {
			throw new NotAuthorizedException('AccessToken is expired.', $request, 401, $exception);
		}

		/** @var object{id: int, tv?: int, type?: string} $payload */
		$payload = $exception->getPayload();

		$this->assertAccessType($payload, $request);

		$request = $this->withUserAttribute($request, $payload->id, $this->tokenVersion($payload));

		return $handler->handle($request);
	}

	private function withUserAttribute(ServerRequestInterface $request, int $userId, int $tokenVersion): ServerRequestInterface
	{
		$user = $this->userProvider->getUser($userId);
		if ($user === null) {
			throw new NotAuthorizedException('User is not authorized.', $request);
		}

		// Reject tokens minted before a credential change bumped the user's version.
		if ($user->tokenVersion !== $tokenVersion) {
			throw new NotAuthorizedException('Token has been revoked.', $request);
		}

		return $request->withAttribute(self::AttributeUser, $user);
	}

	private function tokenVersion(object $token): int
	{
		return isset($token->tv) && is_int($token->tv) ? $token->tv : 0;
	}
}
