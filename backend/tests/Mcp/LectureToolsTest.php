<?php

declare(strict_types=1);

namespace Kytarna\Tests\Mcp;

use Kytarna\Mcp\McpUserContextInterface;
use Kytarna\Mcp\Tool\LectureTools;
use Kytarna\Model\Entity\User;
use Kytarna\Service\Actor\ActorContextInterface;
use Kytarna\Tests\Support\AppHarness;
use Kytarna\Tests\Support\Fixture;
use Kytarna\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LectureTools::class)]
final class LectureToolsTest extends IntegrationTestCase
{
	public function testCreateLectureDefaultsToStartStatusAndIsMarkedAgentCreated(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);

		$lectureTools = $this->bootAs($user);

		$lecture = $lectureTools->createLecture(courseId: $course->id, name: 'Agent lecture');

		self::assertSame('Agent lecture', $lecture->name);

		// A new lecture lands in the first learning status by default.
		self::assertSame('ToLearn', $lecture->status);
		self::assertSame('To Learn', $lecture->statusLabel);

		// Verify the lecture was attributed to an agent (ActorContext was flipped to Agent in bootAs).
		$pdo = AppHarness::pdo();
		$stmt = $pdo->prepare('SELECT created_by_agent FROM lectures WHERE id = :id');
		if ($stmt === false) {
			self::fail('Failed to prepare SELECT statement');
		}
		$stmt->execute(['id' => $lecture->id]);
		self::assertSame(1, (int) $stmt->fetchColumn());
	}

	public function testCreateLectureHonoursStatus(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);

		$lectureTools = $this->bootAs($user);

		$lecture = $lectureTools->createLecture(courseId: $course->id, name: 'Learning lecture', status: 'Learning');
		self::assertSame('Learning lecture', $lecture->name);
		self::assertSame('Learning', $lecture->status);
	}

	public function testMoveLectureByStatus(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);

		$lectureTools = $this->bootAs($user);

		$lecture = $lectureTools->createLecture(courseId: $course->id, name: 'Move me');
		$moved = $lectureTools->moveLecture(lectureId: $lecture->id, status: 'Mastered');

		self::assertSame('Mastered', $moved->status);
		self::assertSame('Mastered', $moved->statusLabel);
	}

	public function testFindLectureByNamePrefersExactMatchOverSubstring(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);

		$lectureTools = $this->bootAs($user);
		$lectureTools->createLecture(courseId: $course->id, name: 'Pay invoice');
		$lectureTools->createLecture(courseId: $course->id, name: 'Pay');

		$found = $lectureTools->findLectureByName($course->id, 'Pay');
		self::assertNotNull($found);
		self::assertSame('Pay', $found->name);
	}

	public function testGetLectureByCode(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);

		$lectureTools = $this->bootAs($user);
		$lecture = $lectureTools->createLecture(courseId: $course->id, name: 'Codeable');

		$fetched = $lectureTools->getLecture($lecture->code);
		self::assertSame($lecture->id, $fetched->id);
	}

	public function testDeleteLecture(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);

		$lectureTools = $this->bootAs($user);
		$lecture = $lectureTools->createLecture(courseId: $course->id, name: 'Doomed');

		$lectureTools->deleteLecture($lecture->id);

		$this->expectException(\RuntimeException::class);
		$lectureTools->getLecture($lecture->id);
	}

	public function testGuitarFieldsCreateUpdateClearAndTuningFilter(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);

		$lectureTools = $this->bootAs($user);

		// Create carries the guitar metadata through to the DTO.
		$lecture = $lectureTools->createLecture(
			courseId: $course->id,
			name: 'Blackbird',
			tuning: 'Drop D',
			capo: 3,
			targetTempoBpm: 96,
			difficulty: 'Intermediate',
		);
		self::assertSame('Drop D', $lecture->tuning);
		self::assertSame(3, $lecture->capo);
		self::assertSame(96, $lecture->targetTempoBpm);
		self::assertSame('Intermediate', $lecture->difficulty);

		// Omitting tuning on update leaves it unchanged; empty string clears it.
		$nameOnly = $lectureTools->updateLecture(lectureId: $lecture->id, name: 'Blackbird 2');
		self::assertSame('Drop D', $nameOnly->tuning);
		$cleared = $lectureTools->updateLecture(lectureId: $lecture->id, tuning: '');
		self::assertNull($cleared->tuning);

		// The tuning filter on list_lectures matches case-insensitive substrings.
		$lectureTools->updateLecture(lectureId: $lecture->id, tuning: 'Drop D');
		$lectureTools->createLecture(courseId: $course->id, name: 'Standard song', tuning: 'E A D G B E');
		self::assertCount(1, $lectureTools->listLectures($course->id, tuning: 'drop d')->lectures);
		self::assertCount(2, $lectureTools->listLectures($course->id)->lectures);
	}

	public function testInvalidDifficultyThrows(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);

		$lectureTools = $this->bootAs($user);

		$this->expectException(\RuntimeException::class);
		$lectureTools->createLecture(courseId: $course->id, name: 'Bad', difficulty: 'Impossible');
	}

	public function testArchiveHidesLectureFromListByDefault(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);

		$lectureTools = $this->bootAs($user);

		$lecture = $lectureTools->createLecture(courseId: $course->id, name: 'Archive me');

		$archived = $lectureTools->archiveLecture($lecture->id);
		self::assertTrue($archived->archived);
		self::assertNotNull($archived->archivedAt);

		// Default list_lectures hides archived; includeArchived=true brings it back.
		self::assertCount(0, $lectureTools->listLectures($course->id)->lectures);
		self::assertCount(1, $lectureTools->listLectures($course->id, includeArchived: true)->lectures);

		$unarchived = $lectureTools->unarchiveLecture($lecture->id);
		self::assertFalse($unarchived->archived);
		self::assertCount(1, $lectureTools->listLectures($course->id)->lectures);
	}

	private function bootAs(User $user): LectureTools
	{
		$ctx = AppHarness::container()->get(McpUserContextInterface::class);
		assert($ctx instanceof McpUserContextInterface);
		$ctx->setUser($user);

		$actor = AppHarness::container()->get(ActorContextInterface::class);
		assert($actor instanceof ActorContextInterface);
		$actor->setAgent('cli', 'Test CLI');

		$lectureTools = AppHarness::container()->get(LectureTools::class);
		assert($lectureTools instanceof LectureTools);

		return $lectureTools;
	}
}
