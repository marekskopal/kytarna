<?php

declare(strict_types=1);

namespace Kytarna\Tests\Controller;

use Kytarna\Controller\SongController;
use Kytarna\Model\Entity\User;
use Kytarna\Tests\Support\Fixture;
use Kytarna\Tests\Support\IntegrationTestCase;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use Laminas\Diactoros\UploadedFile;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use const UPLOAD_ERR_OK;

#[CoversClass(SongController::class)]
final class SongControllerTest extends IntegrationTestCase
{
	public function testSongRestLifecycle(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$course = Fixture::createCourse($owner, $workspace);

		// A lecture in the course consumes the first per-course sequence number.
		$lectureCreate = $this->request(
			'POST',
			'/api/courses/' . $course->id . '/lectures',
			body: ['status' => 'ToLearn', 'name' => 'Lesson one', 'description' => null],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $lectureCreate->getStatusCode());
		$lectureCode = self::stringField($this->jsonBody($lectureCreate)['code']);

		// Create a standalone song: no course, so no code.
		$create = $this->request(
			'POST',
			'/api/songs',
			body: ['name' => 'Wonderwall', 'authorName' => 'Oasis', 'description' => null],
			authenticatedAs: $owner,
		);
		self::assertSame(201, $create->getStatusCode());
		$created = $this->jsonBody($create);
		self::assertSame('Wonderwall', $created['name']);
		self::assertNull($created['code']);
		self::assertNull($created['courseId']);
		self::assertSame('ToLearn', $created['status']);
		self::assertFalse($created['hasCover']);
		$songId = self::intField($created['id']);

		// The library list includes it.
		$list = $this->jsonBody($this->request('GET', '/api/songs', authenticatedAs: $owner));
		self::assertSame(1, $list['count']);
		$listSongs = $list['songs'];
		self::assertIsArray($listSongs);
		self::assertCount(1, $listSongs);

		// Attaching to a course assigns a PREFIX-N code drawn from the same per-course sequence as
		// lectures — so a lecture and a song in the same course never share a code.
		$attach = $this->request(
			'PUT',
			'/api/songs/' . $songId . '/course',
			body: ['courseId' => $course->id],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $attach->getStatusCode());
		$attached = $this->jsonBody($attach);
		self::assertSame($course->id, $attached['courseId']);
		self::assertNotNull($attached['code']);
		$songCode = self::stringField($attached['code']);
		self::assertStringStartsWith($course->prefix . '-', $songCode);
		self::assertNotSame($lectureCode, $songCode);

		// Move it across statuses.
		$move = $this->request(
			'PUT',
			'/api/songs/' . $songId . '/move',
			body: ['status' => 'Learning', 'position' => 0],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $move->getStatusCode());
		self::assertSame('Learning', $this->jsonBody($move)['status']);

		// The course board carries the attached song and the fixed status set (no workflow key).
		$board = $this->jsonBody($this->request('GET', '/api/courses/' . $course->id . '/board', authenticatedAs: $owner));
		self::assertSame(['ToLearn', 'Learning', 'Mastered'], $board['statuses']);
		self::assertArrayNotHasKey('workflow', $board);
		$boardSongs = $board['songs'];
		self::assertIsArray($boardSongs);
		self::assertCount(1, $boardSongs);
		self::assertIsArray($boardSongs[0]);
		self::assertSame($songId, $boardSongs[0]['id']);

		// Archive / unarchive round-trip.
		$archive = $this->request('POST', '/api/songs/' . $songId . '/archive', authenticatedAs: $owner);
		self::assertSame(200, $archive->getStatusCode());
		self::assertNotNull($this->jsonBody($archive)['archivedAt']);

		$unarchive = $this->request('POST', '/api/songs/' . $songId . '/unarchive', authenticatedAs: $owner);
		self::assertSame(200, $unarchive->getStatusCode());
		self::assertNull($this->jsonBody($unarchive)['archivedAt']);

		// Cover upload (multipart), fetch and delete.
		$png = self::tinyPng();
		$upload = $this->uploadCover($songId, $owner, $png);
		self::assertSame(200, $upload->getStatusCode());
		self::assertTrue($this->jsonBody($upload)['hasCover']);

		$getCover = $this->request('GET', '/api/songs/' . $songId . '/cover', authenticatedAs: $owner);
		self::assertSame(200, $getCover->getStatusCode());
		self::assertSame('image/png', $getCover->getHeaderLine('Content-Type'));
		self::assertSame($png, (string) $getCover->getBody());

		$deleteCover = $this->request('DELETE', '/api/songs/' . $songId . '/cover', authenticatedAs: $owner);
		self::assertSame(200, $deleteCover->getStatusCode());
		self::assertFalse($this->jsonBody($deleteCover)['hasCover']);

		$coverGone = $this->request('GET', '/api/songs/' . $songId . '/cover', authenticatedAs: $owner);
		self::assertSame(404, $coverGone->getStatusCode());

		// Detaching returns it to the library and clears the code.
		$detach = $this->request(
			'PUT',
			'/api/songs/' . $songId . '/course',
			body: ['courseId' => null],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $detach->getStatusCode());
		self::assertNull($this->jsonBody($detach)['code']);
		self::assertNull($this->jsonBody($detach)['courseId']);

		// Delete removes it from the library.
		$delete = $this->request('DELETE', '/api/songs/' . $songId, authenticatedAs: $owner);
		self::assertSame(200, $delete->getStatusCode());
		self::assertSame(0, $this->jsonBody($this->request('GET', '/api/songs', authenticatedAs: $owner))['count']);
	}

	private function uploadCover(int $songId, User $owner, string $bytes): ResponseInterface
	{
		$stream = new Stream('php://temp', 'r+');
		$stream->write($bytes);
		$stream->rewind();

		$uploaded = new UploadedFile($stream, strlen($bytes), UPLOAD_ERR_OK, 'cover.png', 'image/png');

		// Multipart uploads are parsed by PHP only for POST, so the cover endpoint is POST (not PUT).
		$request = (new ServerRequest([], [], '/api/songs/' . $songId . '/cover', 'POST'))
			->withUploadedFiles(['file' => $uploaded])
			->withHeader('Authorization', 'Bearer ' . Fixture::accessTokenFor($owner));

		return $this->handler->handle($request);
	}

	private static function tinyPng(): string
	{
		// A 1x1 transparent PNG.
		return (string) base64_decode(
			'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
			true,
		);
	}
}
