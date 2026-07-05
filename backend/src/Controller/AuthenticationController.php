<?php

declare(strict_types=1);

namespace Kytario\Controller;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Kytario\Dto\ConfirmPasswordResetDto;
use Kytario\Dto\CredentialsDto;
use Kytario\Dto\GoogleClientIdDto;
use Kytario\Dto\GoogleLoginDto;
use Kytario\Dto\RefreshTokenDto;
use Kytario\Dto\RequestPasswordResetDto;
use Kytario\Dto\SignUpDto;
use Kytario\Dto\VerifyEmailDto;
use Kytario\Model\Entity\Enum\LocaleEnum;
use Kytario\Model\Entity\User;
use Kytario\Response\ErrorResponse;
use Kytario\Response\NotAuthorizedResponse;
use Kytario\Response\OkResponse;
use Kytario\Route\Routes;
use Kytario\Service\Authentication\AuthenticationServiceInterface;
use Kytario\Service\Authentication\Exception\AccountLockedException;
use Kytario\Service\Authentication\Exception\AuthenticationException;
use Kytario\Service\Authentication\Exception\GoogleAuthException;
use Kytario\Service\Authentication\GoogleAuthServiceInterface;
use Kytario\Service\Provider\EmailVerificationProviderInterface;
use Kytario\Service\Provider\PasswordResetProviderInterface;
use Kytario\Service\Provider\UserProviderInterface;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use Kytario\Service\Realtime\MercureCookieIssuerInterface;
use Kytario\Service\Request\RequestServiceInterface;
use Kytario\Validator\PasswordValidator;
use Kytario\Validator\TextFieldValidator;
use const FILTER_VALIDATE_EMAIL;

final readonly class AuthenticationController
{
	public function __construct(
		private AuthenticationServiceInterface $authenticationService,
		private UserProviderInterface $userProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private PasswordResetProviderInterface $passwordResetProvider,
		private EmailVerificationProviderInterface $emailVerificationProvider,
		private GoogleAuthServiceInterface $googleAuthService,
		private RequestServiceInterface $requestService,
		private MercureCookieIssuerInterface $mercureCookieIssuer,
		private LoggerInterface $logger,
	) {
	}

	#[RoutePost(Routes::AuthenticationLogin->value)]
	public function actionPostLogin(ServerRequestInterface $request): ResponseInterface
	{
		$credentials = $this->requestService->getRequestBodyDto($request, CredentialsDto::class);

		try {
			$auth = $this->authenticationService->authenticate($credentials);
		} catch (AccountLockedException $e) {
			return new ErrorResponse(
				'Too many failed sign-in attempts. Please try again later.',
				429,
				['Retry-After' => (string) $e->retryAfterSeconds],
			);
		} catch (AuthenticationException) {
			return new NotAuthorizedResponse('Email or password is invalid.');
		}

		$user = $this->userProvider->getUser($auth->userId);

		return $this->withMercureCookie(new JsonResponse($auth), $request, $user);
	}

	#[RoutePost(Routes::AuthenticationLogout->value)]
	public function actionPostLogout(ServerRequestInterface $request): ResponseInterface
	{
		// Web JWTs are stateless (revocation is tracked separately), but the HttpOnly Mercure
		// subscriber cookie lives up to 1 h — expire it so the next user on a shared browser
		// cannot resume the previous user's realtime stream. Open route: an expired access
		// token must still be able to log out.
		return (new OkResponse())->withAddedHeader(
			'Set-Cookie',
			$this->mercureCookieIssuer->clear($this->isSecureRequest($request)),
		);
	}

	#[RoutePost(Routes::AuthenticationSignUp->value)]
	public function actionPostSignUp(ServerRequestInterface $request): ResponseInterface
	{
		$signUp = $this->requestService->getRequestBodyDto($request, SignUpDto::class);

		if ($signUp->email === '' || filter_var($signUp->email, FILTER_VALIDATE_EMAIL) === false) {
			return new ErrorResponse('Invalid email address.', 422);
		}

		if (!PasswordValidator::isValid($signUp->password)) {
			return new ErrorResponse('Password must be at least 8 characters and contain uppercase, lowercase, and a digit.', 422);
		}

		try {
			$name = TextFieldValidator::validateName($signUp->name, 'User');
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		// Generic response either way so the endpoint does not reveal whether the
		// email is already registered (user enumeration). The frontend follows up
		// with a normal login call to establish the session for new accounts.
		if ($this->userProvider->getUserByEmail($signUp->email) === null) {
			$locale = $signUp->locale !== null ? LocaleEnum::tryFrom($signUp->locale) ?? LocaleEnum::En : LocaleEnum::En;
			$user = $this->userProvider->createUser($signUp->email, $signUp->password, $name, $locale);

			$this->workspaceProvider->createWorkspace($user, mb_substr($name, 0, 240) . "'s Workspace");

			$this->emailVerificationProvider->requestVerification($user);
		}

		return new OkResponse();
	}

	#[RoutePost(Routes::AuthenticationRefreshToken->value)]
	public function actionPostRefreshToken(ServerRequestInterface $request): ResponseInterface
	{
		$refreshToken = $this->requestService->getRequestBodyDto($request, RefreshTokenDto::class);

		$tokenKey = (string) getenv('AUTHORIZATION_TOKEN_KEY');

		try {
			$decoded = JWT::decode($refreshToken->refreshToken, new Key($tokenKey, AuthenticationServiceInterface::TokenAlgorithm));
		} catch (ExpiredException) {
			return new NotAuthorizedResponse('RefreshToken is expired.');
		} catch (\UnexpectedValueException | \InvalidArgumentException | \DomainException) {
			return new NotAuthorizedResponse('Invalid RefreshToken.');
		}

		// An access token must not be accepted here — it would stretch a stolen 1 h
		// token into a 7 d session. Legacy tokens without the claim expire naturally.
		if (isset($decoded->type) && $decoded->type !== AuthenticationServiceInterface::TokenTypeRefresh) {
			return new NotAuthorizedResponse('Invalid RefreshToken.');
		}

		$user = $this->requestService->getUser($request);

		$decodedTokenVersion = isset($decoded->tv) && is_int($decoded->tv) ? $decoded->tv : 0;
		if ($decoded->id !== $user->id || $decodedTokenVersion !== $user->tokenVersion) {
			return new NotAuthorizedResponse('Invalid RefreshToken.');
		}

		return $this->withMercureCookie(
			new JsonResponse($this->authenticationService->createAuthentication($user)),
			$request,
			$user,
		);
	}

	#[RoutePost(Routes::AuthenticationRequestPasswordReset->value)]
	public function actionPostRequestPasswordReset(ServerRequestInterface $request): ResponseInterface
	{
		$dto = $this->requestService->getRequestBodyDto($request, RequestPasswordResetDto::class);

		$this->passwordResetProvider->requestReset($dto->email);

		return new OkResponse();
	}

	#[RoutePost(Routes::AuthenticationConfirmPasswordReset->value)]
	public function actionPostConfirmPasswordReset(ServerRequestInterface $request): ResponseInterface
	{
		$dto = $this->requestService->getRequestBodyDto($request, ConfirmPasswordResetDto::class);

		if (!PasswordValidator::isValid($dto->password)) {
			return new ErrorResponse('Password must be at least 8 characters and contain uppercase, lowercase, and a digit.', 422);
		}

		$token = $this->passwordResetProvider->findByToken($dto->token);
		if ($token === null) {
			return new ErrorResponse('This reset link is invalid.', 422);
		}

		try {
			$user = $this->passwordResetProvider->confirmReset($token, $dto->password);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return $this->withMercureCookie(
			new JsonResponse($this->authenticationService->createAuthentication($user)),
			$request,
			$user,
		);
	}

	#[RouteGet(Routes::AuthenticationGoogleClientId->value)]
	public function actionGetGoogleClientId(): ResponseInterface
	{
		return new JsonResponse(new GoogleClientIdDto(googleClientId: (string) getenv('GOOGLE_CLIENT_ID')));
	}

	#[RoutePost(Routes::AuthenticationGoogleLogin->value)]
	public function actionPostGoogleLogin(ServerRequestInterface $request): ResponseInterface
	{
		$dto = $this->requestService->getRequestBodyDto($request, GoogleLoginDto::class);

		try {
			$tokenInfo = $this->googleAuthService->verifyIdToken($dto->idToken);
		} catch (GoogleAuthException $e) {
			$this->logger->info('Google login failed: ' . $e->getMessage());

			return new NotAuthorizedResponse('Invalid Google token.');
		}

		$locale = $dto->locale !== null ? LocaleEnum::tryFrom($dto->locale) ?? LocaleEnum::En : LocaleEnum::En;

		$user = $this->userProvider->getUserByGoogleId($tokenInfo->sub);
		if ($user === null) {
			$user = $this->userProvider->getUserByEmail($tokenInfo->email);
			if ($user !== null) {
				$user = $this->userProvider->linkGoogleAccount($user, $tokenInfo->sub);
			} else {
				$user = $this->userProvider->createUserFromGoogle(
					email: $tokenInfo->email,
					name: $tokenInfo->name,
					googleId: $tokenInfo->sub,
					locale: $locale,
				);
				$this->workspaceProvider->createWorkspace($user, $tokenInfo->name . "'s Workspace");
			}
		}

		return $this->withMercureCookie(
			new JsonResponse($this->authenticationService->createAuthentication($user)),
			$request,
			$user,
		);
	}

	#[RoutePost(Routes::AuthenticationVerifyEmail->value)]
	public function actionPostVerifyEmail(ServerRequestInterface $request): ResponseInterface
	{
		$dto = $this->requestService->getRequestBodyDto($request, VerifyEmailDto::class);

		$token = $this->emailVerificationProvider->findByToken($dto->token);
		if ($token === null) {
			return new ErrorResponse('This verification link is invalid.', 422);
		}

		try {
			$this->emailVerificationProvider->confirmVerification($token);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new OkResponse();
	}

	private function withMercureCookie(ResponseInterface $response, ServerRequestInterface $request, ?User $user,): ResponseInterface
	{
		if ($user === null) {
			return $response;
		}

		return $response->withAddedHeader(
			'Set-Cookie',
			$this->mercureCookieIssuer->issue($user, $this->isSecureRequest($request)),
		);
	}

	private function isSecureRequest(ServerRequestInterface $request): bool
	{
		$forwardedProto = $request->getHeader('X-Forwarded-Proto')[0] ?? null;
		if ($forwardedProto !== null) {
			return strtolower($forwardedProto) === 'https';
		}

		return strtolower($request->getUri()->getScheme()) === 'https';
	}
}
