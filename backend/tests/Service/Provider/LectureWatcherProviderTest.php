<?php

declare(strict_types=1);

namespace Kytarna\Tests\Service\Provider;

use Kytarna\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Repository\LectureRepository;
use Kytarna\Service\Provider\LectureWatcherProvider;
use Kytarna\Service\Provider\LectureWatcherProviderInterface;
use Kytarna\Tests\Support\Fixture;
use Kytarna\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LectureWatcherProvider::class)]
final class LectureWatcherProviderTest extends IntegrationTestCase
{
	public function testWatchIsIdempotentAndUnwatchRemoves(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$course = Fixture::createCourse($owner, $workspace);
		$lecture = $this->createLecture($owner, $course->id, 'Watched lecture');

		$provider = $this->watcherProvider();

		self::assertFalse($provider->isWatching($lecture, $owner));

		$provider->watch($lecture, $owner);
		$provider->watch($lecture, $owner);

		self::assertTrue($provider->isWatching($lecture, $owner));
		self::assertCount(1, $provider->listWatchers($lecture));
		self::assertSame([$owner->id], $provider->listWatcherUserIds($lecture));

		$provider->unwatch($lecture, $owner);

		self::assertFalse($provider->isWatching($lecture, $owner));
		self::assertCount(0, $provider->listWatchers($lecture));
	}

	public function testDeleteAllForLectureClearsEveryWatcher(): void
	{
		$owner = Fixture::createUser();
		$member = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);
		$course = Fixture::createCourse($owner, $workspace);
		$lecture = $this->createLecture($owner, $course->id, 'Shared lecture');

		$provider = $this->watcherProvider();
		$provider->watch($lecture, $owner);
		$provider->watch($lecture, $member);
		self::assertCount(2, $provider->listWatchers($lecture));

		$provider->deleteAllForLecture($lecture);

		self::assertCount(0, $provider->listWatchers($lecture));
	}

	private function watcherProvider(): LectureWatcherProviderInterface
	{
		$provider = $this->container->get(LectureWatcherProviderInterface::class);
		assert($provider instanceof LectureWatcherProviderInterface);
		return $provider;
	}

	private function createLecture(User $author, int $courseId, string $name): Lecture
	{
		$response = $this->request(
			'POST',
			'/api/courses/' . $courseId . '/lectures',
			body: ['status' => 'ToLearn', 'name' => $name, 'description' => null],
			authenticatedAs: $author,
		);
		$lectureId = self::intField($this->jsonBody($response)['id']);

		$lectureRepository = $this->container->get(LectureRepository::class);
		assert($lectureRepository instanceof LectureRepository);
		$lecture = $lectureRepository->findById($lectureId);
		assert($lecture instanceof Lecture);
		return $lecture;
	}
}
