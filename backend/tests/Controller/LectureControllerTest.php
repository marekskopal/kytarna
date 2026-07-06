<?php

declare(strict_types=1);

namespace Kytarna\Tests\Controller;

use Kytarna\Controller\LectureController;
use Kytarna\Model\Entity\Course;
use Kytarna\Model\Entity\User;
use Kytarna\Tests\Support\Fixture;
use Kytarna\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LectureController::class)]
final class LectureControllerTest extends IntegrationTestCase
{
	public function testCreateListAndGetLectureRoundTrip(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$course = Fixture::createCourse($owner, $workspace);

		$create = $this->request(
			'POST',
			'/api/courses/' . $course->id . '/lectures',
			body: [
				'status' => 'ToLearn',
				'name' => 'Write tests',
				'description' => 'Cover the codebase',
			],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $create->getStatusCode());
		$lecture = $this->jsonBody($create);
		self::assertSame('Write tests', $lecture['name']);
		self::assertSame('ToLearn', $lecture['status']);
		self::assertNotEmpty($lecture['code']);
		$lectureId = self::intField($lecture['id']);
		$lectureCode = self::stringField($lecture['code']);

		// List under course
		$list = $this->request('GET', '/api/courses/' . $course->id . '/lectures', authenticatedAs: $owner);
		self::assertCount(1, $this->jsonList($list));

		// Get by numeric ID (routes accept either int ID or PREFIX-N code)
		$getById = $this->request('GET', '/api/lectures/' . $lectureId, authenticatedAs: $owner);
		self::assertSame(200, $getById->getStatusCode());

		// Get by code form
		$getByCode = $this->request('GET', '/api/lectures/' . $lectureCode, authenticatedAs: $owner);
		self::assertSame(200, $getByCode->getStatusCode());

		// Workspace-wide listing returns same lecture
		$wsList = $this->request('GET', '/api/lectures', authenticatedAs: $owner);
		self::assertSame(200, $wsList->getStatusCode());
		$payload = $this->jsonBody($wsList);
		self::assertSame(1, $payload['count']);
		$payloadLectures = $payload['lectures'];
		self::assertIsArray($payloadLectures);
		self::assertCount(1, $payloadLectures);
	}

	public function testMoveLectureBetweenStatuses(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$course = Fixture::createCourse($owner, $workspace);

		$create = $this->request(
			'POST',
			'/api/courses/' . $course->id . '/lectures',
			body: ['status' => 'ToLearn', 'name' => 'Move me', 'description' => null],
			authenticatedAs: $owner,
		);
		$code = self::stringField($this->jsonBody($create)['code']);

		$move = $this->request(
			'PUT',
			'/api/lectures/' . $code . '/move',
			body: ['status' => 'Learning', 'position' => 0],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $move->getStatusCode());
		self::assertSame('Learning', $this->jsonBody($move)['status']);
	}

	public function testWorkspaceListPaginationAndStatusFilter(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$course = Fixture::createCourse($owner, $workspace);

		for ($i = 0; $i < 4; $i++) {
			$this->request(
				'POST',
				'/api/courses/' . $course->id . '/lectures',
				body: ['status' => $i % 2 === 0 ? 'ToLearn' : 'Learning', 'name' => 'T' . $i, 'description' => null],
				authenticatedAs: $owner,
			);
		}

		$filtered = $this->request('GET', '/api/lectures?statuses=ToLearn&limit=10', authenticatedAs: $owner);
		self::assertSame(200, $filtered->getStatusCode());
		self::assertSame(2, $this->jsonBody($filtered)['count']);

		$paged = $this->request('GET', '/api/lectures?limit=2&offset=0', authenticatedAs: $owner);
		$pagedBody = $this->jsonBody($paged);
		$pagedLectures = $pagedBody['lectures'];
		self::assertIsArray($pagedLectures);
		self::assertCount(2, $pagedLectures);
		self::assertSame(4, $pagedBody['count']);
	}

	public function testGuitarFieldsRoundTrip(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$course = Fixture::createCourse($owner, $workspace);

		// Create with guitar metadata — it round-trips in the response.
		$create = $this->request('POST', '/api/courses/' . $course->id . '/lectures', body: [
			'status' => 'ToLearn',
			'name' => 'Blackbird',
			'description' => null,
			'tuning' => 'Drop D',
			'capo' => 3,
			'targetTempoBpm' => 96,
			'difficulty' => 'Intermediate',
		], authenticatedAs: $owner);
		self::assertSame(200, $create->getStatusCode());
		$created = $this->jsonBody($create);
		self::assertSame('Drop D', $created['tuning']);
		self::assertSame(3, $created['capo']);
		self::assertSame(96, $created['targetTempoBpm']);
		self::assertSame('Intermediate', $created['difficulty']);
		$code = self::stringField($created['code']);

		// Updating guitar metadata round-trips too.
		$update = $this->request('PUT', '/api/lectures/' . $code, body: [
			'status' => 'ToLearn',
			'name' => 'Blackbird',
			'description' => null,
			'tuning' => 'Standard',
			'capo' => null,
			'targetTempoBpm' => 108,
			'difficulty' => 'Advanced',
		], authenticatedAs: $owner);
		self::assertSame(200, $update->getStatusCode());
		$updated = $this->jsonBody($update);
		self::assertSame('Standard', $updated['tuning']);
		self::assertNull($updated['capo']);
		self::assertSame('Advanced', $updated['difficulty']);
	}

	public function testInvalidDifficultyIsRejectedWith422(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$course = Fixture::createCourse($owner, $workspace);

		// An unknown difficulty must answer 422, not crash with an uncaught enum error (500).
		$response = $this->request('POST', '/api/courses/' . $course->id . '/lectures', body: [
			'status' => 'ToLearn',
			'name' => 'Bad difficulty',
			'description' => null,
			'difficulty' => 'Impossible',
		], authenticatedAs: $owner);
		self::assertSame(422, $response->getStatusCode());
	}

	public function testDeleteLectureRemovesIt(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$course = Fixture::createCourse($owner, $workspace);

		$create = $this->request(
			'POST',
			'/api/courses/' . $course->id . '/lectures',
			body: ['status' => 'ToLearn', 'name' => 'Doomed', 'description' => null],
			authenticatedAs: $owner,
		);
		$code = self::stringField($this->jsonBody($create)['code']);

		$delete = $this->request('DELETE', '/api/lectures/' . $code, authenticatedAs: $owner);
		self::assertSame(200, $delete->getStatusCode());

		$get = $this->request('GET', '/api/lectures/' . $code, authenticatedAs: $owner);
		self::assertSame(404, $get->getStatusCode());
	}

	public function testGetLectureFromAnotherWorkspaceIsNotFound(): void
	{
		[, $intruder, $lectureCode] = $this->seedCrossWorkspace();

		$response = $this->request('GET', '/api/lectures/' . $lectureCode, authenticatedAs: $intruder);
		self::assertSame(404, $response->getStatusCode());

		// Also assert the workspace-wide list does not leak the foreign lecture.
		$list = $this->request('GET', '/api/lectures', authenticatedAs: $intruder);
		self::assertSame(0, $this->jsonBody($list)['count']);
	}

	public function testCreateLectureInAnotherWorkspaceCourseIsNotFound(): void
	{
		[$courseInA, $intruder] = $this->seedCrossWorkspace();

		$response = $this->request(
			'POST',
			'/api/courses/' . $courseInA->id . '/lectures',
			body: ['status' => 'ToLearn', 'name' => 'Hijack', 'description' => null],
			authenticatedAs: $intruder,
		);
		self::assertSame(404, $response->getStatusCode());
	}

	public function testUpdateLectureFromAnotherWorkspaceIsNotFound(): void
	{
		[, $intruder, $lectureCode] = $this->seedCrossWorkspace();

		$response = $this->request(
			'PUT',
			'/api/lectures/' . $lectureCode,
			body: ['name' => 'Renamed by intruder', 'description' => null],
			authenticatedAs: $intruder,
		);
		self::assertSame(404, $response->getStatusCode());
	}

	public function testMoveLectureFromAnotherWorkspaceIsNotFound(): void
	{
		[, $intruder, $lectureCode] = $this->seedCrossWorkspace();

		$response = $this->request(
			'PUT',
			'/api/lectures/' . $lectureCode . '/move',
			body: ['status' => 'Learning', 'position' => 0],
			authenticatedAs: $intruder,
		);
		self::assertSame(404, $response->getStatusCode());
	}

	public function testDeleteLectureFromAnotherWorkspaceIsNotFound(): void
	{
		[, $intruder, $lectureCode] = $this->seedCrossWorkspace();

		$response = $this->request('DELETE', '/api/lectures/' . $lectureCode, authenticatedAs: $intruder);
		self::assertSame(404, $response->getStatusCode());
	}

	/**
	 * Build the two-workspace scaffold used by every cross-workspace test:
	 * an owner with workspace A holding one lecture; a separate intruder in workspace B.
	 *
	 * @return array{0:Course,1:User,2:string}
	 *   [course in A, intruder user, lecture code in A]
	 */
	private function seedCrossWorkspace(): array
	{
		$owner = Fixture::createUser(email: 'owner@example.com');
		$workspaceA = Fixture::createWorkspace($owner, 'A');
		$courseInA = Fixture::createCourse($owner, $workspaceA);

		$create = $this->request(
			'POST',
			'/api/courses/' . $courseInA->id . '/lectures',
			body: ['status' => 'ToLearn', 'name' => 'Owner lecture', 'description' => null],
			authenticatedAs: $owner,
		);
		assert($create->getStatusCode() === 200);
		$lectureCode = self::stringField($this->jsonBody($create)['code']);

		$intruder = Fixture::createUser(email: 'intruder@example.com');
		Fixture::createWorkspace($intruder, 'B');

		return [$courseInA, $intruder, $lectureCode];
	}

	public function testArchiveAndUnarchiveLectureRoundTrip(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$course = Fixture::createCourse($owner, $workspace);

		$created = $this->request(
			'POST',
			'/api/courses/' . $course->id . '/lectures',
			body: ['status' => 'ToLearn', 'name' => 'Archive me', 'description' => null],
			authenticatedAs: $owner,
		);
		$lectureId = self::intField($this->jsonBody($created)['id']);

		// Archiving stamps archivedAt and the lecture drops out of the default workspace list.
		$archive = $this->request('POST', '/api/lectures/' . $lectureId . '/archive', authenticatedAs: $owner);
		self::assertSame(200, $archive->getStatusCode());
		self::assertNotNull($this->jsonBody($archive)['archivedAt']);

		$activeList = $this->request('GET', '/api/lectures', authenticatedAs: $owner);
		self::assertSame(0, $this->jsonBody($activeList)['count']);

		// archived=archived returns only archived; archived=all returns both.
		$archivedList = $this->request('GET', '/api/lectures?archived=archived', authenticatedAs: $owner);
		self::assertSame(1, $this->jsonBody($archivedList)['count']);

		$allList = $this->request('GET', '/api/lectures?archived=all', authenticatedAs: $owner);
		self::assertSame(1, $this->jsonBody($allList)['count']);

		// Archived lectures are hidden from the board.
		$board = $this->request('GET', '/api/courses/' . $course->id . '/board', authenticatedAs: $owner);
		$boardLectures = $this->jsonBody($board)['lectures'];
		self::assertIsArray($boardLectures);
		self::assertCount(0, $boardLectures);

		// Unarchiving restores it everywhere.
		$unarchive = $this->request('POST', '/api/lectures/' . $lectureId . '/unarchive', authenticatedAs: $owner);
		self::assertSame(200, $unarchive->getStatusCode());
		self::assertNull($this->jsonBody($unarchive)['archivedAt']);

		$restored = $this->request('GET', '/api/lectures', authenticatedAs: $owner);
		self::assertSame(1, $this->jsonBody($restored)['count']);
	}

	public function testSearchTreatsLikeWildcardsLiterally(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$course = Fixture::createCourse($owner, $workspace);

		foreach (['Plain lecture', '100% done', 'under_score'] as $name) {
			$create = $this->request(
				'POST',
				'/api/courses/' . $course->id . '/lectures',
				body: ['status' => 'ToLearn', 'name' => $name],
				authenticatedAs: $owner,
			);
			self::assertSame(200, $create->getStatusCode());
		}

		// "%" must match only the literal percent sign, not every row.
		$percent = $this->request('GET', '/api/lectures?search=' . urlencode('%'), authenticatedAs: $owner);
		self::assertSame(1, $this->jsonBody($percent)['count']);

		// "_" must match only the literal underscore, not any single character.
		$underscore = $this->request('GET', '/api/lectures?search=' . urlencode('_'), authenticatedAs: $owner);
		self::assertSame(1, $this->jsonBody($underscore)['count']);
	}

	public function testCreateLectureWithEmptyNameIsRejected(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$course = Fixture::createCourse($owner, $workspace);

		$response = $this->request(
			'POST',
			'/api/courses/' . $course->id . '/lectures',
			body: ['status' => 'ToLearn', 'name' => '   '],
			authenticatedAs: $owner,
		);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testCreateLectureWithOverlongNameIsRejected(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$course = Fixture::createCourse($owner, $workspace);

		$response = $this->request(
			'POST',
			'/api/courses/' . $course->id . '/lectures',
			body: ['status' => 'ToLearn', 'name' => str_repeat('a', 256)],
			authenticatedAs: $owner,
		);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testCreateLectureWithOverlongDescriptionIsRejected(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$course = Fixture::createCourse($owner, $workspace);

		$response = $this->request(
			'POST',
			'/api/courses/' . $course->id . '/lectures',
			body: [
				'status' => 'ToLearn',
				'name' => 'Valid name',
				'description' => str_repeat('a', 50001),
			],
			authenticatedAs: $owner,
		);

		self::assertSame(422, $response->getStatusCode());
	}
}
