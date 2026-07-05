<?php

declare(strict_types=1);

namespace Kytario\Tests\Support;

use Firebase\JWT\JWT;
use Kytario\Model\Entity\Enum\LocaleEnum;
use Kytario\Model\Entity\Enum\SystemRoleEnum;
use Kytario\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytario\Model\Entity\Project;
use Kytario\Model\Entity\User;
use Kytario\Model\Entity\Workspace;
use Kytario\Model\Repository\UserRepository;
use Kytario\Service\Authentication\AuthenticationServiceInterface;
use Kytario\Service\Provider\ProjectProviderInterface;
use Kytario\Service\Provider\UserProviderInterface;
use Kytario\Service\Provider\WorkspaceProviderInterface;

final class Fixture
{
	public static int $userCounter = 0;

	public static function reset(): void
	{
		self::$userCounter = 0;
	}

	public static function createUser(
		?string $email = null,
		string $password = 'TestPass1!',
		string $name = 'Test User',
		LocaleEnum $locale = LocaleEnum::En,
		SystemRoleEnum $systemRole = SystemRoleEnum::User,
		bool $emailVerified = true,
	): User {
		self::$userCounter++;
		$email ??= 'user' . self::$userCounter . '@example.com';

		$userProvider = AppHarness::container()->get(UserProviderInterface::class);
		assert($userProvider instanceof UserProviderInterface);

		$user = $userProvider->createUser($email, $password, $name, $locale);

		if ($systemRole !== SystemRoleEnum::User || $emailVerified) {
			$user->systemRole = $systemRole;
			$user->emailVerified = $emailVerified;
			$repository = AppHarness::container()->get(UserRepository::class);
			assert($repository instanceof UserRepository);
			$repository->persist($user);
		}

		return $user;
	}

	public static function createWorkspace(User $owner, string $name = 'Test Workspace'): Workspace
	{
		$provider = AppHarness::container()->get(WorkspaceProviderInterface::class);
		assert($provider instanceof WorkspaceProviderInterface);
		return $provider->createWorkspace($owner, $name);
	}

	public static function addMember(Workspace $workspace, User $user, WorkspaceRoleEnum $role): void
	{
		$provider = AppHarness::container()->get(WorkspaceProviderInterface::class);
		assert($provider instanceof WorkspaceProviderInterface);
		$provider->addMember($workspace, $user, $role);
	}

	public static function createProject(User $author, Workspace $workspace, string $name = 'Test Project'): Project
	{
		$provider = AppHarness::container()->get(ProjectProviderInterface::class);
		assert($provider instanceof ProjectProviderInterface);
		return $provider->createProject($author, $workspace, $name, null);
	}

	public static function accessTokenFor(User $user): string
	{
		return self::tokenFor($user, AuthenticationServiceInterface::TokenTypeAccess, time() + 3600);
	}

	public static function expiredAccessTokenFor(User $user): string
	{
		return self::tokenFor($user, AuthenticationServiceInterface::TokenTypeAccess, time() - 60);
	}

	public static function refreshTokenFor(User $user): string
	{
		return self::tokenFor($user, AuthenticationServiceInterface::TokenTypeRefresh, time() + 3600);
	}

	public static function expiredRefreshTokenFor(User $user): string
	{
		return self::tokenFor($user, AuthenticationServiceInterface::TokenTypeRefresh, time() - 60);
	}

	private static function tokenFor(User $user, string $type, int $expiresAt): string
	{
		$key = (string) getenv('AUTHORIZATION_TOKEN_KEY');
		return JWT::encode(
			['id' => $user->id, 'tv' => $user->tokenVersion, 'type' => $type, 'exp' => $expiresAt],
			$key,
			AuthenticationServiceInterface::TokenAlgorithm,
		);
	}
}
