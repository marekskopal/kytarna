<?php

declare(strict_types=1);

namespace Kytarna\Controller;

use DateTimeImmutable;
use Kytarna\Dto\ChangePasswordDto;
use Kytarna\Dto\CurrentUserUpdateDto;
use Kytarna\Dto\UserDto;
use Kytarna\Model\Entity\Enum\LocaleEnum;
use Kytarna\Model\Entity\Enum\ThemeEnum;
use Kytarna\Model\Repository\UserRepository;
use Kytarna\Response\ConflictResponse;
use Kytarna\Response\ErrorResponse;
use Kytarna\Response\NotAuthorizedResponse;
use Kytarna\Response\OkResponse;
use Kytarna\Route\Routes;
use Kytarna\Service\Auth\CurrentUserDeletionServiceInterface;
use Kytarna\Service\Auth\SoleOwnerException;
use Kytarna\Service\Auth\UserDataExportServiceInterface;
use Kytarna\Service\Provider\EmailVerificationProviderInterface;
use Kytarna\Service\Provider\SavedViewProviderInterface;
use Kytarna\Service\Provider\UserProviderInterface;
use Kytarna\Service\Request\RequestServiceInterface;
use Kytarna\Validator\PasswordValidator;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePatch;
use MarekSkopal\Router\Attribute\RoutePost;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final readonly class CurrentUserController
{
	public function __construct(
		private UserProviderInterface $userProvider,
		private EmailVerificationProviderInterface $emailVerificationProvider,
		private CurrentUserDeletionServiceInterface $currentUserDeletionService,
		private UserDataExportServiceInterface $userDataExportService,
		private SavedViewProviderInterface $savedViewProvider,
		private RequestServiceInterface $requestService,
		private UserRepository $userRepository,
	) {
	}

	#[RouteGet(Routes::CurrentUser->value)]
	public function actionGetCurrentUser(ServerRequestInterface $request): ResponseInterface
	{
		return new JsonResponse(UserDto::fromEntity($this->requestService->getUser($request)));
	}

	#[RoutePatch(Routes::CurrentUser->value)]
	public function actionPatchCurrentUser(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$dto = $this->requestService->getRequestBodyDto($request, CurrentUserUpdateDto::class);

		$locale = null;
		if ($dto->locale !== null) {
			$locale = LocaleEnum::tryFrom($dto->locale);
			if ($locale === null) {
				return new ErrorResponse('Unsupported locale.', 422);
			}
		}

		$theme = null;
		if ($dto->theme !== null) {
			$theme = ThemeEnum::tryFrom($dto->theme);
			if ($theme === null) {
				return new ErrorResponse('Unsupported theme.', 422);
			}
		}

		$name = $dto->name !== null ? trim($dto->name) : null;
		if ($name === '') {
			$name = null;
		}

		$updated = $this->userProvider->updateUser($user, $name, $locale, $theme);

		if ($dto->defaultSavedViewIdProvided) {
			if ($dto->defaultSavedViewId !== null) {
				$view = $this->savedViewProvider->getViewForUser($dto->defaultSavedViewId, $updated);
				if ($view === null) {
					return new ErrorResponse('Saved view not found.', 422);
				}
			}
			$updated = $this->userProvider->updateDefaultSavedViewId($updated, $dto->defaultSavedViewId);
		}

		return new JsonResponse(UserDto::fromEntity($updated));
	}

	#[RoutePost(Routes::CurrentUserPassword->value)]
	public function actionPostPassword(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$dto = $this->requestService->getRequestBodyDto($request, ChangePasswordDto::class);

		if ($user->password === null || !password_verify($dto->currentPassword, $user->password)) {
			return new NotAuthorizedResponse('Current password is incorrect.');
		}

		if (!PasswordValidator::isValid($dto->newPassword)) {
			return new ErrorResponse('Password must be at least 8 characters and contain uppercase, lowercase, and a digit.', 422);
		}

		$this->userProvider->updateUserPassword($user, $dto->newPassword);

		return new OkResponse();
	}

	#[RoutePost(Routes::CurrentUserResendVerification->value)]
	public function actionPostResendVerification(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);

		if ($user->emailVerified) {
			return new ErrorResponse('Email is already verified.', 422);
		}

		$this->emailVerificationProvider->requestVerification($user);

		return new OkResponse();
	}

	#[RoutePost(Routes::CurrentUserOnboardingComplete->value)]
	public function actionPostOnboardingComplete(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);

		if ($user->onboardingCompletedAt === null) {
			$user->onboardingCompletedAt = new DateTimeImmutable();
			$this->userRepository->persist($user);
		}

		return new JsonResponse(UserDto::fromEntity($user));
	}

	#[RouteDelete(Routes::CurrentUser->value)]
	public function actionDeleteCurrentUser(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);

		try {
			$this->currentUserDeletionService->deleteSelf($user);
		} catch (SoleOwnerException $e) {
			return new JsonResponse(
				[
					'code' => 409,
					'message' => $e->getMessage(),
					'workspaces' => array_map(
						static fn (int $id, string $name): array => ['id' => $id, 'name' => $name],
						$e->workspaceIds(),
						$e->workspaceNames(),
					),
				],
				409,
			);
		} catch (RuntimeException $e) {
			return new ConflictResponse($e->getMessage());
		}

		return new OkResponse();
	}

	#[RouteGet(Routes::CurrentUserExport->value)]
	public function actionGetExport(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$payload = $this->userDataExportService->export($user);

		return (new JsonResponse($payload))->withHeader(
			'Content-Disposition',
			sprintf('attachment; filename="kytarna-export-%d.json"', $user->id),
		);
	}
}
