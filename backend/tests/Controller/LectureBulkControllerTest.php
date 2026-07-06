<?php

declare(strict_types=1);

namespace Kytarna\Tests\Controller;

use Kytarna\Controller\LectureBulkController;
use Kytarna\Model\Entity\Enum\EventTypeEnum;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Model\Repository\EventRepository;
use Kytarna\Tests\Support\Fixture;
use Kytarna\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LectureBulkController::class)]
final class LectureBulkControllerTest extends IntegrationTestCase
{
	public function testBulkMoveSucceedsAndAppendsToTarget(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$course = Fixture::createCourse($owner, $workspace);

		$ids = $this->createLectures($owner, $course->id, 'ToLearn', ['A', 'B', 'C']);

		$response = $this->request(
			'POST',
			'/api/lectures/bulk',
			body: ['ids' => $ids, 'op' => 'move', 'payload' => ['status' => 'Learning']],
			authenticatedAs: $owner,
		);

		self::assertSame(200, $response->getStatusCode());
		$body = $this->jsonBody($response);
		self::assertSame($ids, $body['succeeded']);
		self::assertSame([], $body['skipped']);

		// All three lectures are now in the target column, in input order.
		foreach ($ids as $id) {
			$lecture = $this->jsonBody($this->request('GET', '/api/lectures/' . $id, authenticatedAs: $owner));
			self::assertSame('Learning', $lecture['status']);
		}

		$this->assertExactlyOneBulkEvent($workspace, 'move', $ids);
	}

	public function testBulkPartialSkipForUnknownAndOutOfWorkspaceIds(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$course = Fixture::createCourse($owner, $workspace);
		$ids = $this->createLectures($owner, $course->id, 'ToLearn', ['Mine']);
		$mineId = $ids[0];

		// Lecture in a foreign workspace owned by someone else.
		$other = Fixture::createUser(email: 'other@example.com');
		$otherWorkspace = Fixture::createWorkspace($other, 'Other');
		$otherCourse = Fixture::createCourse($other, $otherWorkspace);
		$foreignIds = $this->createLectures($other, $otherCourse->id, 'ToLearn', ['Theirs']);
		$foreignId = $foreignIds[0];

		$missingId = 999999;

		$response = $this->request(
			'POST',
			'/api/lectures/bulk',
			body: [
				'ids' => [$mineId, $foreignId, $missingId],
				'op' => 'move',
				'payload' => ['status' => 'Learning'],
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
		$ids = $this->createLectures($owner, $course->id, 'ToLearn', ['X', 'Y']);

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
		$ids = $this->createLectures($owner, $course->id, 'ToLearn', ['T1', 'T2']);

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
	private function createLectures(User $owner, int $courseId, string $status, array $names): array
	{
		$ids = [];
		foreach ($names as $name) {
			$create = $this->request(
				'POST',
				'/api/courses/' . $courseId . '/lectures',
				body: ['status' => $status, 'name' => $name, 'description' => null],
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
}
