<?php

declare(strict_types=1);

namespace Kytarna\Tests\Mcp;

use Kytarna\Mcp\McpUserContextInterface;
use Kytarna\Mcp\Tool\SongTools;
use Kytarna\Model\Entity\User;
use Kytarna\Service\Actor\ActorContextInterface;
use Kytarna\Tests\Support\AppHarness;
use Kytarna\Tests\Support\Fixture;
use Kytarna\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(SongTools::class)]
final class SongToolsTest extends IntegrationTestCase
{
	public function testStandaloneSongLifecycle(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);

		$songTools = $this->bootAs($user);

		// A standalone song has no course and therefore no code.
		$song = $songTools->createSong(name: 'Wonderwall', authorName: 'Oasis');
		self::assertSame('Wonderwall', $song->name);
		self::assertNull($song->code);
		self::assertNull($song->courseId);
		self::assertSame('ToLearn', $song->status);
		self::assertSame('To Learn', $song->statusLabel);
		$songId = $song->id;

		// Attaching to a course places it on the board and assigns a PREFIX-N code.
		$attached = $songTools->addSongToCourse(songId: $songId, courseId: $course->id);
		self::assertSame($course->id, $attached->courseId);
		self::assertSame($course->name, $attached->courseName);
		self::assertNotNull($attached->code);
		self::assertStringStartsWith($course->prefix . '-', $attached->code);

		// Moving to a different status is reflected in the DTO.
		$moved = $songTools->moveSong(songId: $songId, status: 'Mastered');
		self::assertSame('Mastered', $moved->status);
		self::assertSame('Mastered', $moved->statusLabel);

		// The status filter on list_songs matches the destination status only.
		$mastered = $songTools->listSongs(status: 'Mastered')->songs;
		self::assertCount(1, $mastered);
		self::assertSame($songId, $mastered[0]->id);
		self::assertCount(0, $songTools->listSongs(status: 'ToLearn')->songs);

		// Detaching returns it to the library and clears the code.
		$removed = $songTools->removeSongFromCourse(songId: $songId);
		self::assertNull($removed->code);
		self::assertNull($removed->courseId);

		// Deleting removes it for good.
		$songTools->deleteSong($songId);
		$this->expectException(RuntimeException::class);
		$songTools->getSong($songId);
	}

	private function bootAs(User $user): SongTools
	{
		$ctx = AppHarness::container()->get(McpUserContextInterface::class);
		assert($ctx instanceof McpUserContextInterface);
		$ctx->setUser($user);

		$actor = AppHarness::container()->get(ActorContextInterface::class);
		assert($actor instanceof ActorContextInterface);
		$actor->setAgent('cli', 'Test CLI');

		$songTools = AppHarness::container()->get(SongTools::class);
		assert($songTools instanceof SongTools);

		return $songTools;
	}
}
