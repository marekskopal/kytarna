<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use SensitiveParameter;
use Ukolio\Model\Entity\Enum\LocaleEnum;
use Ukolio\Model\Entity\Enum\ThemeEnum;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\UserRepository;
use const PASSWORD_BCRYPT;

final readonly class UserProvider implements UserProviderInterface
{
	public function __construct(private UserRepository $userRepository)
	{
	}

	public function getUser(int $userId): ?User
	{
		return $this->userRepository->findUserById($userId);
	}

	public function getUserByEmail(string $email): ?User
	{
		return $this->userRepository->findUserByEmail($email);
	}

	public function getUserByGoogleId(string $googleId): ?User
	{
		return $this->userRepository->findUserByGoogleId($googleId);
	}

	public function createUser(
		#[SensitiveParameter] string $email,
		#[SensitiveParameter] string $password,
		string $name,
		LocaleEnum $locale = LocaleEnum::En,
	): User {
		$now = new DateTimeImmutable();
		$user = new User(
			email: $email,
			password: password_hash($password, PASSWORD_BCRYPT),
			name: $name,
			locale: $locale,
		);
		$user->createdAt = $now;
		$user->updatedAt = $now;

		$this->userRepository->persist($user);

		return $user;
	}

	public function createUserFromGoogle(string $email, string $name, string $googleId, LocaleEnum $locale = LocaleEnum::En): User
	{
		$now = new DateTimeImmutable();
		$user = new User(email: $email, password: null, name: $name, locale: $locale, emailVerified: true);
		$user->googleId = $googleId;
		$user->createdAt = $now;
		$user->updatedAt = $now;

		$this->userRepository->persist($user);

		return $user;
	}

	public function linkGoogleAccount(User $user, string $googleId): User
	{
		$user->googleId = $googleId;
		$user->updatedAt = new DateTimeImmutable();
		$this->userRepository->persist($user);

		return $user;
	}

	public function updateUser(User $user, ?string $name = null, ?LocaleEnum $locale = null, ?ThemeEnum $theme = null): User
	{
		if ($name !== null) {
			$user->name = $name;
		}
		if ($locale !== null) {
			$user->locale = $locale;
		}
		if ($theme !== null) {
			$user->theme = $theme;
		}
		$user->updatedAt = new DateTimeImmutable();
		$this->userRepository->persist($user);

		return $user;
	}

	public function updateDefaultSavedViewId(User $user, ?int $defaultSavedViewId): User
	{
		$user->defaultSavedViewId = $defaultSavedViewId;
		$user->updatedAt = new DateTimeImmutable();
		$this->userRepository->persist($user);

		return $user;
	}

	public function updateUserPassword(User $user, #[SensitiveParameter] string $newPassword): User
	{
		$user->password = password_hash($newPassword, PASSWORD_BCRYPT);
		// Invalidate every outstanding access + refresh token: their `tv` claim no longer
		// matches, so a stolen token stops working the moment the password changes.
		$user->tokenVersion++;
		$user->updatedAt = new DateTimeImmutable();
		$this->userRepository->persist($user);

		return $user;
	}

	public function markEmailVerified(User $user): User
	{
		$user->emailVerified = true;
		$user->updatedAt = new DateTimeImmutable();
		$this->userRepository->persist($user);

		return $user;
	}
}
