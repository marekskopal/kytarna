<?php

declare(strict_types=1);

namespace Kytario\Tests\Mcp;

use Kytario\Mcp\McpUserContextInterface;
use Kytario\Mcp\Tool\LectureTools;
use Kytario\Mcp\Tool\WorkflowTools;
use Kytario\Model\Entity\User;
use Kytario\Service\Actor\ActorContextInterface;
use Kytario\Tests\Support\AppHarness;
use Kytario\Tests\Support\Fixture;
use Kytario\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LectureTools::class)]
#[CoversClass(WorkflowTools::class)]
final class LectureToolsTest extends IntegrationTestCase
{
	public function testCreateLectureDefaultsToStartStatusAndIsMarkedAgentCreated(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);

		[$lectureTools, $workflowTools] = $this->bootAs($user);

		$lecture = $lectureTools->createLecture(courseId: $course->id, name: 'Agent lecture');

		self::assertSame('Agent lecture', $lecture->name);

		// The Start status is the first in the default workflow.
		$statuses = $workflowTools->listStatuses($course->id);
		self::assertSame($statuses->statuses[0]->id, $lecture->statusId);

		// Verify the lecture was attributed to an agent (ActorContext was flipped to Agent in bootAs).
		$pdo = AppHarness::pdo();
		$stmt = $pdo->prepare('SELECT created_by_agent FROM lectures WHERE id = :id');
		if ($stmt === false) {
			self::fail('Failed to prepare SELECT statement');
		}
		$stmt->execute(['id' => $lecture->id]);
		self::assertSame(1, (int) $stmt->fetchColumn());
	}

	public function testCreateLectureHonoursStatusName(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);

		[$lectureTools] = $this->bootAs($user);

		$lecture = $lectureTools->createLecture(courseId: $course->id, name: 'Learning lecture', statusName: 'Learning');
		self::assertSame('Learning lecture', $lecture->name);
	}

	public function testMoveLectureByStatusName(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);

		[$lectureTools, $workflowTools] = $this->bootAs($user);

		$lecture = $lectureTools->createLecture(courseId: $course->id, name: 'Move me');
		$moved = $lectureTools->moveLecture(lectureId: $lecture->id, statusName: 'Mastered');

		$statuses = $workflowTools->listStatuses($course->id);
		$doneId = null;
		foreach ($statuses->statuses as $status) {
			if ($status->name === 'Mastered') {
				$doneId = $status->id;
			}
		}
		self::assertSame($doneId, $moved->statusId);
	}

	public function testFindLectureByNamePrefersExactMatchOverSubstring(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);

		[$lectureTools] = $this->bootAs($user);
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

		[$lectureTools] = $this->bootAs($user);
		$lecture = $lectureTools->createLecture(courseId: $course->id, name: 'Codeable');

		$fetched = $lectureTools->getLecture($lecture->code);
		self::assertSame($lecture->id, $fetched->id);
	}

	public function testDeleteLecture(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);

		[$lectureTools] = $this->bootAs($user);
		$lecture = $lectureTools->createLecture(courseId: $course->id, name: 'Doomed');

		$lectureTools->deleteLecture($lecture->id);

		$this->expectException(\RuntimeException::class);
		$lectureTools->getLecture($lecture->id);
	}

	public function testStartDateCreateUpdateAndClear(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);

		[$lectureTools] = $this->bootAs($user);

		// Create carries startDate through to the DTO.
		$lecture = $lectureTools->createLecture(courseId: $course->id, name: 'Spanning', startDate: '2026-05-10');
		self::assertSame('2026-05-10', $lecture->startDate);

		// Omitting startDate on update leaves it unchanged; empty string clears it.
		$nameOnly = $lectureTools->updateLecture(lectureId: $lecture->id, name: 'Spanning 2');
		self::assertSame('2026-05-10', $nameOnly->startDate);
		$cleared = $lectureTools->updateLecture(lectureId: $lecture->id, startDate: '');
		self::assertNull($cleared->startDate);
	}

	public function testArchiveHidesLectureFromListByDefault(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);

		[$lectureTools] = $this->bootAs($user);

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

	/** @return array{0:LectureTools,1:WorkflowTools} */
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

		$workflowTools = AppHarness::container()->get(WorkflowTools::class);
		assert($workflowTools instanceof WorkflowTools);

		return [$lectureTools, $workflowTools];
	}
}
