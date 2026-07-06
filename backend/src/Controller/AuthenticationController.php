<?php

declare(strict_types=1);

namespace Kytarna\Controller;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Kytarna\Dto\ConfirmPasswordResetDto;
use Kytarna\Dto\CredentialsDto;
use Kytarna\Dto\GoogleClientIdDto;
use Kytarna\Dto\GoogleLoginDto;
use Kytarna\Dto\RefreshTokenDto;
use Kytarna\Dto\RequestPasswordResetDto;
use Kytarna\Dto\SignUpDto;
use Kytarna\Dto\VerifyEmailDto;
use Kytarna\Model\Entity\Enum\LocaleEnum;
use Kytarna\Response\ErrorResponse;
use Kytarna\Response\NotAuthorizedResponse;
use Kytarna\Response\OkResponse;
use Kytarna\Route\Routes;
use Kytarna\Service\Authentication\AuthenticationServiceInterface;
use Kytarna\Service\Authentication\Exception\AccountLockedException;
use Kytarna\Service\Authentication\Exception\AuthenticationException;
use Kytarna\Service\Authentication\Exception\GoogleAuthException;
use Kytarna\Service\Authentication\GoogleAuthServiceInterface;
use Kytarna\Service\Provider\EmailVerificationProviderInterface;
use Kytarna\Service\Provider\PasswordResetProviderInterface;
use Kytarna\Service\Provider\UserProviderInterface;
use Kytarna\Service\Request\RequestServiceInterface;
use Kytarna\Validator\PasswordValidator;
use Kytarna\Validator\TextFieldValidator;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use const FILTER_VALIDATE_EMAIL;

final readonly class AuthenticationController
{
	public function __construct(
		private AuthenticationServiceInterface $authenticationService,
		private UserProviderInterface $userProvider,
		private PasswordResetProviderInterface $passwordResetProvider,
		private EmailVerificationProviderInterface $emailVerificationProvider,
		private GoogleAuthServiceInterface $googleAuthService,
		private RequestServiceInterface $requestService,
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

		return new JsonResponse($auth);
	}

	#[RoutePost(Routes::AuthenticationLogout->value)]
	public function actionPostLogout(): ResponseInterface
	{
		// Web JWTs are stateless (revocation is tracked separately); logout is an open route so
		// an expired access token can still log out. Nothing server-side to clear.
		return new OkResponse();
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

			// No workspace is auto-created: onboarding lets the user choose to create their own
			// workspace (Teacher / self-taught) or join a teacher's workspace as a Student.
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

		return new JsonResponse($this->authenticationService->createAuthentication($user));
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

		return new JsonResponse($this->authenticationService->createAuthentication($user));
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
			$existing = $this->userProvider->getUserByEmail($tokenInfo->email);
			// No workspace is auto-created here — the user picks Teacher/Student during onboarding.
			$user = $existing !== null
				? $this->userProvider->linkGoogleAccount($existing, $tokenInfo->sub)
				: $this->userProvider->createUserFromGoogle(
					email: $tokenInfo->email,
					name: $tokenInfo->name,
					googleId: $tokenInfo->sub,
					locale: $locale,
				);
		}

		return new JsonResponse($this->authenticationService->createAuthentication($user));
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
}
