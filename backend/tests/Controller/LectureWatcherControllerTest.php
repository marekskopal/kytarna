<?php

declare(strict_types=1);

namespace Kytario\Tests\Controller;

use Kytario\Controller\LectureWatcherController;
use Kytario\Model\Entity\User;
use Kytario\Model\Repository\StatusRepository;
use Kytario\Model\Repository\WorkflowRepository;
use Kytario\Tests\Support\Fixture;
use Kytario\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LectureWatcherController::class)]
final class LectureWatcherControllerTest extends IntegrationTestCase
{
	public function testWatchListAndUnwatch(): void
	{
		$owner = Fixture::createUser(name: 'Owner');
		$workspace = Fixture::createWorkspace($owner);
		$course = Fixture::createCourse($owner, $workspace);
		$lectureId = $this->createLecture($owner, $course->id, 'Lecture');

		$initial = $this->jsonBody($this->request('GET', '/api/lectures/' . $lectureId . '/watchers', authenticatedAs: $owner));
		self::assertFalse($initial['watching']);
		self::assertCount(0, $this->watchers($initial));

		$watched = $this->jsonBody($this->request('POST', '/api/lectures/' . $lectureId . '/watch', authenticatedAs: $owner));
		self::assertTrue($watched['watching']);
		$watchers = $this->watchers($watched);
		self::assertCount(1, $watchers);
		self::assertSame($owner->id, $watchers[0]['userId']);

		// Idempotent.
		$again = $this->jsonBody($this->request('POST', '/api/lectures/' . $lectureId . '/watch', authenticatedAs: $owner));
		self::assertCount(1, $this->watchers($again));

		$unwatched = $this->jsonBody($this->request('DELETE', '/api/lectures/' . $lectureId . '/watch', authenticatedAs: $owner));
		self::assertFalse($unwatched['watching']);
		self::assertCount(0, $this->watchers($unwatched));
	}

	public function testForeignLectureIsNotFound(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$course = Fixture::createCourse($owner, $workspace);
		$lectureId = $this->createLecture($owner, $course->id, 'Private lecture');

		$outsider = Fixture::createUser();
		Fixture::createWorkspace($outsider);

		self::assertSame(
			404,
			$this->request('GET', '/api/lectures/' . $lectureId . '/watchers', authenticatedAs: $outsider)->getStatusCode(),
		);
		self::assertSame(
			404,
			$this->request('POST', '/api/lectures/' . $lectureId . '/watch', authenticatedAs: $outsider)->getStatusCode(),
		);
	}

	/**
	 * @param array<string, mixed> $body
	 * @return list<array<array-key, mixed>>
	 */
	private function watchers(array $body): array
	{
		$watchers = $body['watchers'];
		self::assertIsArray($watchers);
		$result = [];
		foreach ($watchers as $watcher) {
			self::assertIsArray($watcher);
			$result[] = $watcher;
		}
		return $result;
	}

	private function createLecture(User $author, int $courseId, string $name): int
	{
		$response = $this->request(
			'POST',
			'/api/courses/' . $courseId . '/lectures',
			body: ['statusId' => $this->firstStatusId($courseId), 'name' => $name, 'description' => null],
			authenticatedAs: $author,
		);
		return self::intField($this->jsonBody($response)['id']);
	}

	private function firstStatusId(int $courseId): int
	{
		$workflowRepo = $this->container->get(WorkflowRepository::class);
		assert($workflowRepo instanceof WorkflowRepository);
		$workflow = $workflowRepo->findByCourse($courseId);
		assert($workflow !== null);

		$statusRepo = $this->container->get(StatusRepository::class);
		assert($statusRepo instanceof StatusRepository);
		foreach ($statusRepo->findByWorkflow($workflow->id) as $status) {
			return $status->id;
		}

		self::fail('Course has no statuses.');
	}
}
