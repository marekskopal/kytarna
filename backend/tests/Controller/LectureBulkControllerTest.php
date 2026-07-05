<?php

declare(strict_types=1);

namespace Kytario\Tests\Controller;

use Kytario\Controller\LectureBulkController;
use Kytario\Model\Entity\Enum\EventTypeEnum;
use Kytario\Model\Entity\User;
use Kytario\Model\Entity\Workspace;
use Kytario\Model\Repository\EventRepository;
use Kytario\Model\Repository\StatusRepository;
use Kytario\Model\Repository\WorkflowRepository;
use Kytario\Tests\Support\Fixture;
use Kytario\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LectureBulkController::class)]
final class LectureBulkControllerTest extends IntegrationTestCase
{
	public function testBulkMoveSucceedsAndAppendsToTarget(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$course = Fixture::createCourse($owner, $workspace);
		[$todoId, $inProgressId] = $this->statusIds($course->id);

		$ids = $this->createLectures($owner, $course->id, $todoId, ['A', 'B', 'C']);

		$response = $this->request(
			'POST',
			'/api/lectures/bulk',
			body: ['ids' => $ids, 'op' => 'move', 'payload' => ['statusId' => $inProgressId]],
			authenticatedAs: $owner,
		);

		self::assertSame(200, $response->getStatusCode());
		$body = $this->jsonBody($response);
		self::assertSame($ids, $body['succeeded']);
		self::assertSame([], $body['skipped']);

		// All three lectures are now in the target column, in input order.
		foreach ($ids as $id) {
			$lecture = $this->jsonBody($this->request('GET', '/api/lectures/' . $id, authenticatedAs: $owner));
			self::assertSame($inProgressId, $lecture['statusId']);
		}

		$this->assertExactlyOneBulkEvent($workspace, 'move', $ids);
	}

	public function testBulkPartialSkipForUnknownAndOutOfWorkspaceIds(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$course = Fixture::createCourse($owner, $workspace);
		[$todoId, $inProgressId] = $this->statusIds($course->id);
		$ids = $this->createLectures($owner, $course->id, $todoId, ['Mine']);
		$mineId = $ids[0];

		// Lecture in a foreign workspace owned by someone else.
		$other = Fixture::createUser(email: 'other@example.com');
		$otherWorkspace = Fixture::createWorkspace($other, 'Other');
		$otherCourse = Fixture::createCourse($other, $otherWorkspace);
		$otherTodoId = $this->firstStatusId($otherCourse->id);
		$foreignIds = $this->createLectures($other, $otherCourse->id, $otherTodoId, ['Theirs']);
		$foreignId = $foreignIds[0];

		$missingId = 999999;

		$response = $this->request(
			'POST',
			'/api/lectures/bulk',
			body: [
				'ids' => [$mineId, $foreignId, $missingId],
				'op' => 'move',
				'payload' => ['statusId' => $inProgressId],
			],
			authenticatedAs: $owner,
		);

		self::assertSame(200, $response->getStatusCode());
		$body = $this->jsonBody($response);
		self::assertSame([$mineId], $body['succeeded']);
		$skipped = $body['skipped'];
		self::assertIsArray($skipped);
		$reasons = array_column($skipped, 'reason', 'id');
		self::assertSame('out_of_workspace', $reasons[$foreignId]);
		self::assertSame('not_found', $reasons[$missingId]);
	}

	public function testInvalidOpReturns422(): void
	{
		$owner = Fixture::createUser();
		Fixture::createWorkspace($owner);

		$response = $this->request(
			'POST',
			'/api/lectures/bulk',
			body: ['ids' => [1], 'op' => 'rename'],
			authenticatedAs: $owner,
		);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testEmptyIdsReturns422(): void
	{
		$owner = Fixture::createUser();
		Fixture::createWorkspace($owner);

		$response = $this->request(
			'POST',
			'/api/lectures/bulk',
			body: ['ids' => [], 'op' => 'delete'],
			authenticatedAs: $owner,
		);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testTooManyIdsReturns422(): void
	{
		$owner = Fixture::createUser();
		Fixture::createWorkspace($owner);

		$ids = range(1, 201);

		$response = $this->request(
			'POST',
			'/api/lectures/bulk',
			body: ['ids' => $ids, 'op' => 'delete'],
			authenticatedAs: $owner,
		);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testNoWorkspaceReturns422(): void
	{
		// no workspace, no current selection
		$loner = Fixture::createUser();

		$response = $this->request(
			'POST',
			'/api/lectures/bulk',
			body: ['ids' => [1], 'op' => 'delete'],
			authenticatedAs: $loner,
		);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testBulkDeleteRemovesLecturesAndWritesSingleEvent(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$course = Fixture::createCourse($owner, $workspace);
		$todoId = $this->firstStatusId($course->id);
		$ids = $this->createLectures($owner, $course->id, $todoId, ['X', 'Y']);

		$response = $this->request(
			'POST',
			'/api/lectures/bulk',
			body: ['ids' => $ids, 'op' => 'delete'],
			authenticatedAs: $owner,
		);

		self::assertSame(200, $response->getStatusCode());
		self::assertSame($ids, $this->jsonBody($response)['succeeded']);

		foreach ($ids as $id) {
			$get = $this->request('GET', '/api/lectures/' . $id, authenticatedAs: $owner);
			self::assertSame(404, $get->getStatusCode());
		}

		$this->assertExactlyOneBulkEvent($workspace, 'delete', $ids);
	}

	public function testBulkTagIsAdditive(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$course = Fixture::createCourse($owner, $workspace);
		$todoId = $this->firstStatusId($course->id);
		$ids = $this->createLectures($owner, $course->id, $todoId, ['T1', 'T2']);

		$tagId = $this->createTag($workspace, 'urgent');

		$response = $this->request(
			'POST',
			'/api/lectures/bulk',
			body: ['ids' => $ids, 'op' => 'tag', 'payload' => ['tagIds' => [$tagId]]],
			authenticatedAs: $owner,
		);

		self::assertSame(200, $response->getStatusCode());

		foreach ($ids as $id) {
			$lecture = $this->jsonBody($this->request('GET', '/api/lectures/' . $id, authenticatedAs: $owner));
			self::assertSame([$tagId], $lecture['tagIds']);
		}
	}

	/**
	 * @param list<string> $names
	 * @return list<int>
	 */
	private function createLectures(User $owner, int $courseId, int $statusId, array $names): array
	{
		$ids = [];
		foreach ($names as $name) {
			$create = $this->request(
				'POST',
				'/api/courses/' . $courseId . '/lectures',
				body: ['statusId' => $statusId, 'name' => $name, 'description' => null],
				authenticatedAs: $owner,
			);
			self::assertSame(200, $create->getStatusCode(), 'create lecture ' . $name);
			$ids[] = self::intField($this->jsonBody($create)['id']);
		}
		return $ids;
	}

	private function createTag(Workspace $workspace, string $name): int
	{
		$response = $this->request(
			'POST',
			'/api/workspaces/' . $workspace->id . '/tags',
			body: ['name' => $name, 'color' => '#ff0000'],
			authenticatedAs: $workspace->owner,
		);
		self::assertSame(200, $response->getStatusCode(), 'create tag ' . $name);
		return self::intField($this->jsonBody($response)['id']);
	}

	/** @param list<int> $expectedIds */
	private function assertExactlyOneBulkEvent(Workspace $workspace, string $expectedOp, array $expectedIds): void
	{
		$eventRepo = $this->container->get(EventRepository::class);
		assert($eventRepo instanceof EventRepository);
		$matching = [];
		foreach ($eventRepo->findByWorkspace($workspace->id, null, 50, 0) as $event) {
			if ($event->type === EventTypeEnum::LecturesBulkUpdated) {
				$matching[] = $event;
			}
		}
		self::assertCount(1, $matching, 'Expected exactly one LecturesBulkUpdated event');
		$event = $matching[0];
		self::assertSame($workspace->id, $event->workspaceId);
		self::assertNull($event->course);
		self::assertNull($event->lectureId);
		$meta = json_decode($event->metadata, true);
		self::assertIsArray($meta);
		self::assertSame($expectedOp, $meta['op']);
		self::assertSame($expectedIds, $meta['succeededIds']);
	}

	/** @return array{0:int,1:int} */
	private function statusIds(int $courseId): array
	{
		$workflowRepo = $this->container->get(WorkflowRepository::class);
		assert($workflowRepo instanceof WorkflowRepository);
		$workflow = $workflowRepo->findByCourse($courseId);
		assert($workflow !== null);
		$statusRepo = $this->container->get(StatusRepository::class);
		assert($statusRepo instanceof StatusRepository);
		$statuses = [];
		foreach ($statusRepo->findByWorkflow($workflow->id) as $status) {
			$statuses[] = $status->id;
		}
		return [$statuses[0], $statuses[1]];
	}

	private function firstStatusId(int $courseId): int
	{
		return $this->statusIds($courseId)[0];
	}
}
