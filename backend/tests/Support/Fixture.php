<?php

declare(strict_types=1);

namespace Kytario\Tests\Support;

use DateTimeImmutable;
use Firebase\JWT\JWT;
use Kytario\Model\Entity\Course;
use Kytario\Model\Entity\Enum\LocaleEnum;
use Kytario\Model\Entity\Enum\StatusTypeEnum;
use Kytario\Model\Entity\Enum\SystemRoleEnum;
use Kytario\Model\Entity\Enum\TabSourceTypeEnum;
use Kytario\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytario\Model\Entity\Lecture;
use Kytario\Model\Entity\LectureLink;
use Kytario\Model\Entity\ProgressEntry;
use Kytario\Model\Entity\Status;
use Kytario\Model\Entity\Tab;
use Kytario\Model\Entity\User;
use Kytario\Model\Entity\Workspace;
use Kytario\Model\Repository\StatusRepository;
use Kytario\Model\Repository\TabRepository;
use Kytario\Model\Repository\UserRepository;
use Kytario\Model\Repository\WorkflowRepository;
use Kytario\Service\Authentication\AuthenticationServiceInterface;
use Kytario\Service\Provider\CourseProviderInterface;
use Kytario\Service\Provider\LectureProviderInterface;
use Kytario\Service\Provider\LinkProviderInterface;
use Kytario\Service\Provider\ProgressProviderInterface;
use Kytario\Service\Provider\UserProviderInterface;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use RuntimeException;

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

	public static function createCourse(User $author, Workspace $workspace, string $name = 'Test Course'): Course
	{
		$provider = AppHarness::container()->get(CourseProviderInterface::class);
		assert($provider instanceof CourseProviderInterface);
		return $provider->createCourse($author, $workspace, $name, null);
	}

	public static function startStatusForCourse(Course $course): Status
	{
		$workflowRepo = AppHarness::container()->get(WorkflowRepository::class);
		assert($workflowRepo instanceof WorkflowRepository);
		$workflow = $workflowRepo->findByCourse($course->id);
		if ($workflow === null) {
			throw new RuntimeException('Course has no workflow.');
		}

		$statusRepo = AppHarness::container()->get(StatusRepository::class);
		assert($statusRepo instanceof StatusRepository);
		$first = null;
		foreach ($statusRepo->findByWorkflow($workflow->id) as $status) {
			$first ??= $status;
			if ($status->type === StatusTypeEnum::Start) {
				return $status;
			}
		}
		if ($first === null) {
			throw new RuntimeException('Course workflow has no statuses.');
		}
		return $first;
	}

	public static function createLecture(User $author, Course $course, string $name = 'Test Lecture'): Lecture
	{
		$provider = AppHarness::container()->get(LectureProviderInterface::class);
		assert($provider instanceof LectureProviderInterface);
		return $provider->createLecture($author, $course, self::startStatusForCourse($course), $name, null);
	}

	/** Persists a Tab directly (bypassing tab-service validation) — for tests that need an existing tab. */
	public static function createTab(
		Lecture $lecture,
		string $name = 'Main riff',
		string $alphaTex = ':4 0.6 2.6 3.6 | 0.5',
		?int $tempo = 120,
		?string $tuning = 'E A D G B E',
		?int $trackCount = 1,
	): Tab {
		$repository = AppHarness::container()->get(TabRepository::class);
		assert($repository instanceof TabRepository);

		$now = new DateTimeImmutable();
		$tab = new Tab(
			lecture: $lecture,
			name: $name,
			alphatexContent: $alphaTex,
			sourceType: TabSourceTypeEnum::Authored,
			originalFile: null,
			tempo: $tempo,
			tuning: $tuning,
			trackCount: $trackCount,
		);
		$tab->createdAt = $now;
		$tab->updatedAt = $now;
		$repository->persist($tab);

		return $tab;
	}

	public static function createProgressEntry(
		User $author,
		Lecture $lecture,
		string $practicedAt = '2026-06-01',
		?string $note = null,
		?int $tempoBpm = null,
		?int $durationMinutes = null,
	): ProgressEntry {
		$provider = AppHarness::container()->get(ProgressProviderInterface::class);
		assert($provider instanceof ProgressProviderInterface);
		return $provider->createEntry(
			$author,
			$lecture,
			new DateTimeImmutable($practicedAt),
			$note,
			$tempoBpm,
			$durationMinutes,
		);
	}

	public static function createLectureLink(
		User $author,
		Lecture $lecture,
		string $url = 'https://youtu.be/dQw4w9WgXcQ',
		?string $label = null,
		?string $kind = null,
		?int $timestampSeconds = null,
	): LectureLink {
		$provider = AppHarness::container()->get(LinkProviderInterface::class);
		assert($provider instanceof LinkProviderInterface);
		return $provider->addLink($author, $lecture, $url, $label, $kind, $timestampSeconds);
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
