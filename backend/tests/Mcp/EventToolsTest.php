<?php

declare(strict_types=1);

namespace Kytarna\Tests\Mcp;

use Kytarna\Mcp\McpUserContextInterface;
use Kytarna\Mcp\Tool\EventTools;
use Kytarna\Mcp\Tool\LectureTools;
use Kytarna\Model\Entity\User;
use Kytarna\Service\Actor\ActorContextInterface;
use Kytarna\Tests\Support\AppHarness;
use Kytarna\Tests\Support\Fixture;
use Kytarna\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(EventTools::class)]
final class EventToolsTest extends IntegrationTestCase
{
	public function testListEventsReturnsWorkspaceEventsNewestFirst(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);

		[$lectureTools, $eventTools] = $this->bootAs($user);

		$lecture = $lectureTools->createLecture(courseId: $course->id, name: 'Ship it');
		$lectureTools->moveLecture(lectureId: $lecture->id, statusName: 'Mastered');

		$events = $eventTools->listEvents()->events;
		self::assertNotEmpty($events);
		// Newest first: the move is the most recent event.
		self::assertSame('LectureMoved', $events[0]->type);
		self::assertSame($lecture->id, $events[0]->lectureId);
		$meta = $events[0]->metadata;
		self::assertIsArray($meta);
		self::assertSame('Mastered', $meta['toStatusName']);
	}

	public function testListEventsFiltersByType(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);

		[$lectureTools, $eventTools] = $this->bootAs($user);

		$lecture = $lectureTools->createLecture(courseId: $course->id, name: 'Filter me');
		$lectureTools->moveLecture(lectureId: $lecture->id, statusName: 'Mastered');

		$moved = $eventTools->listEvents(type: 'LectureMoved')->events;
		self::assertCount(1, $moved);
		self::assertSame('LectureMoved', $moved[0]->type);
		self::assertSame($lecture->id, $moved[0]->lectureId);
		$meta = $moved[0]->metadata;
		self::assertIsArray($meta);
		self::assertSame('Mastered', $meta['toStatusName']);
	}

	public function testListLectureEventsScopesToSingleLectureByCode(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);

		[$lectureTools, $eventTools] = $this->bootAs($user);

		$kept = $lectureTools->createLecture(courseId: $course->id, name: 'Keep');
		$other = $lectureTools->createLecture(courseId: $course->id, name: 'Other');
		$lectureTools->moveLecture(lectureId: $other->id, statusName: 'Mastered');

		$events = $eventTools->listLectureEvents(lectureId: $kept->code)->events;
		self::assertNotEmpty($events);
		foreach ($events as $event) {
			self::assertSame($kept->id, $event->lectureId);
		}
	}

	public function testArchivingRecordsLectureArchivedEvent(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);

		[$lectureTools, $eventTools] = $this->bootAs($user);

		$lecture = $lectureTools->createLecture(courseId: $course->id, name: 'Archive me');
		// Exercises the events.type ENUM accepting LectureArchived (see AddLectureArchivedEventTypes migration).
		$lectureTools->archiveLecture(lectureId: $lecture->id);

		$events = $eventTools->listEvents(lectureId: $lecture->id, type: 'LectureArchived')->events;
		self::assertCount(1, $events);
		self::assertSame($lecture->id, $events[0]->lectureId);
	}

	public function testUnknownEventTypeThrows(): void
	{
		$user = Fixture::createUser();
		Fixture::createWorkspace($user);

		[, $eventTools] = $this->bootAs($user);

		$this->expectException(RuntimeException::class);
		$eventTools->listEvents(type: 'NotARealType');
	}

	/** @return array{0: LectureTools, 1: EventTools} */
	private function bootAs(User $user): array
	{
		$ctx = AppHarness::container()->get(McpUserContextInterface::class);
		assert($ctx instanceof McpUserContextInterface);
		$ctx->setUser($user);

		$actor = AppHarness::container()->get(ActorContextInterface::class);
		assert($actor instanceof ActorContextInterface);
		$actor->setAgent('cli', 'Test CLI');

		$lectureTools = AppHarness::container()->get(LectureTools::class);
		assert($lectureTools instanceof LectureTools);

		$eventTools = AppHarness::container()->get(EventTools::class);
		assert($eventTools instanceof EventTools);

		return [$lectureTools, $eventTools];
	}
}
