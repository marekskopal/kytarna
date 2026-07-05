<?php

declare(strict_types=1);

namespace Ukolio\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use Ukolio\Controller\TaskCommentController;
use Ukolio\Model\Entity\Enum\EventTypeEnum;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\EventRepository;
use Ukolio\Model\Repository\StatusRepository;
use Ukolio\Model\Repository\WorkflowRepository;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(TaskCommentController::class)]
final class TaskCommentControllerTest extends IntegrationTestCase
{
	public function testCreateListEditAndDelete(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$taskId = $this->createTask($owner, $project->id, 'Commented task');

		$created = $this->jsonBody($this->request(
			'POST',
			'/api/tasks/' . $taskId . '/comments',
			body: ['body' => 'First take'],
			authenticatedAs: $owner,
		));
		self::assertSame('First take', $created['body']);
		self::assertNull($created['parentCommentId']);
		self::assertFalse($created['edited']);

		$commentId = self::intField($created['id']);
		$edited = $this->request(
			'PUT',
			'/api/task-comments/' . $commentId,
			body: ['body' => 'Second take'],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $edited->getStatusCode());
		$editedBody = $this->jsonBody($edited);
		self::assertSame('Second take', $editedBody['body']);
		self::assertTrue($editedBody['edited']);

		$list = $this->jsonList($this->request('GET', '/api/tasks/' . $taskId . '/comments', authenticatedAs: $owner));
		self::assertCount(1, $list);
		self::assertSame('Second take', $list[0]['body']);

		$delete = $this->request('DELETE', '/api/task-comments/' . $commentId, authenticatedAs: $owner);
		self::assertSame(200, $delete->getStatusCode());
		self::assertCount(0, $this->jsonList($this->request('GET', '/api/tasks/' . $taskId . '/comments', authenticatedAs: $owner)));
	}

	public function testEmptyBodyIsRejected(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$taskId = $this->createTask($owner, $project->id, 'Task');

		self::assertSame(422, $this->request(
			'POST',
			'/api/tasks/' . $taskId . '/comments',
			body: ['body' => '   '],
			authenticatedAs: $owner,
		)->getStatusCode());
	}

	public function testOnlyAuthorMayEdit(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$member = Fixture::createUser();
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);
		$project = Fixture::createProject($owner, $workspace);
		$taskId = $this->createTask($owner, $project->id, 'Task');

		$comment = $this->jsonBody($this->request(
			'POST',
			'/api/tasks/' . $taskId . '/comments',
			body: ['body' => 'Owner words'],
			authenticatedAs: $owner,
		));
		$commentId = self::intField($comment['id']);

		// A fellow member (even though they may delete) may not rewrite someone else's comment.
		self::assertSame(401, $this->request(
			'PUT',
			'/api/task-comments/' . $commentId,
			body: ['body' => 'Hijacked'],
			authenticatedAs: $member,
		)->getStatusCode());
	}

	public function testRepliesAreClampedToOneLevel(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$taskId = $this->createTask($owner, $project->id, 'Threaded task');

		$root = $this->jsonBody($this->request(
			'POST',
			'/api/tasks/' . $taskId . '/comments',
			body: ['body' => 'Root'],
			authenticatedAs: $owner,
		));
		$rootId = self::intField($root['id']);

		$reply = $this->jsonBody($this->request(
			'POST',
			'/api/tasks/' . $taskId . '/comments',
			body: ['body' => 'Reply', 'parentCommentId' => $rootId],
			authenticatedAs: $owner,
		));
		self::assertSame($rootId, $reply['parentCommentId']);
		$replyId = self::intField($reply['id']);

		// Replying to a reply attaches to the root, not the reply (single-level threads).
		$nested = $this->jsonBody($this->request(
			'POST',
			'/api/tasks/' . $taskId . '/comments',
			body: ['body' => 'Nested', 'parentCommentId' => $replyId],
			authenticatedAs: $owner,
		));
		self::assertSame($rootId, $nested['parentCommentId']);
	}

	public function testDeletingParentRemovesReplies(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$taskId = $this->createTask($owner, $project->id, 'Task');

		$root = $this->jsonBody($this->request(
			'POST',
			'/api/tasks/' . $taskId . '/comments',
			body: ['body' => 'Root'],
			authenticatedAs: $owner,
		));
		$rootId = self::intField($root['id']);
		$this->request(
			'POST',
			'/api/tasks/' . $taskId . '/comments',
			body: ['body' => 'Reply', 'parentCommentId' => $rootId],
			authenticatedAs: $owner,
		);

		$before = $this->jsonList($this->request('GET', '/api/tasks/' . $taskId . '/comments', authenticatedAs: $owner));
		self::assertCount(2, $before);

		$this->request('DELETE', '/api/task-comments/' . $rootId, authenticatedAs: $owner);
		$after = $this->jsonList($this->request('GET', '/api/tasks/' . $taskId . '/comments', authenticatedAs: $owner));
		self::assertCount(0, $after);
	}

	public function testMentionsRecordWorkspaceMembersOnly(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$member = Fixture::createUser();
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);
		$project = Fixture::createProject($owner, $workspace);
		$taskId = $this->createTask($owner, $project->id, 'Task');

		$bogusId = $member->id + 9999;
		$this->request(
			'POST',
			'/api/tasks/' . $taskId . '/comments',
			body: ['body' => 'Ping @[Member](user:' . $member->id . ') and @[Ghost](user:' . $bogusId . ')'],
			authenticatedAs: $owner,
		);

		$meta = $this->latestEventMetadata($workspace->id, EventTypeEnum::TaskCommentAdded);
		self::assertArrayHasKey('mentionedUserIds', $meta);
		self::assertSame([$member->id], $meta['mentionedUserIds']);
	}

	public function testMentionFanOutIsCappedPerComment(): void
	{
		// A single comment must not be able to mass-notify an unbounded number of members.
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$taskId = $this->createTask($owner, $project->id, 'Task');

		$mentions = '';
		for ($i = 0; $i < 55; $i++) {
			$member = Fixture::createUser();
			Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);
			$mentions .= '@[Member](user:' . $member->id . ') ';
		}

		$this->request('POST', '/api/tasks/' . $taskId . '/comments', body: ['body' => trim($mentions)], authenticatedAs: $owner);

		$meta = $this->latestEventMetadata($workspace->id, EventTypeEnum::TaskCommentAdded);
		self::assertArrayHasKey('mentionedUserIds', $meta);
		self::assertIsArray($meta['mentionedUserIds']);
		self::assertLessThanOrEqual(50, count($meta['mentionedUserIds']));
		self::assertCount(50, $meta['mentionedUserIds']);
	}

	public function testMissingBodyIsRejectedWith422(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$taskId = $this->createTask($owner, $project->id, 'Task');

		// No body key — must answer 422, not crash with an undefined-key error (500).
		$response = $this->request(
			'POST',
			'/api/tasks/' . $taskId . '/comments',
			body: ['parentCommentId' => null],
			authenticatedAs: $owner,
		);
		self::assertSame(422, $response->getStatusCode());
	}

	public function testForeignTaskCommentsAreNotFound(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$taskId = $this->createTask($owner, $project->id, 'Private task');

		$outsider = Fixture::createUser();
		Fixture::createWorkspace($outsider);

		self::assertSame(404, $this->request('GET', '/api/tasks/' . $taskId . '/comments', authenticatedAs: $outsider)->getStatusCode());
		self::assertSame(404, $this->request(
			'POST',
			'/api/tasks/' . $taskId . '/comments',
			body: ['body' => 'Sneaky'],
			authenticatedAs: $outsider,
		)->getStatusCode());
	}

	/** @return array<array-key, mixed> */
	private function latestEventMetadata(int $workspaceId, EventTypeEnum $type): array
	{
		$eventRepo = $this->container->get(EventRepository::class);
		assert($eventRepo instanceof EventRepository);
		foreach ($eventRepo->findByWorkspace($workspaceId, null, 50, 0) as $event) {
			if ($event->type === $type) {
				$meta = json_decode($event->metadata, true);
				self::assertIsArray($meta);
				return $meta;
			}
		}
		self::fail('No ' . $type->value . ' event recorded.');
	}

	private function createTask(User $author, int $projectId, string $name): int
	{
		$response = $this->request(
			'POST',
			'/api/projects/' . $projectId . '/tasks',
			body: ['statusId' => $this->firstStatusId($projectId), 'name' => $name, 'description' => null, 'priority' => 'Medium'],
			authenticatedAs: $author,
		);
		return self::intField($this->jsonBody($response)['id']);
	}

	private function firstStatusId(int $projectId): int
	{
		$workflowRepo = $this->container->get(WorkflowRepository::class);
		assert($workflowRepo instanceof WorkflowRepository);
		$workflow = $workflowRepo->findByProject($projectId);
		assert($workflow !== null);

		$statusRepo = $this->container->get(StatusRepository::class);
		assert($statusRepo instanceof StatusRepository);
		foreach ($statusRepo->findByWorkflow($workflow->id) as $status) {
			return $status->id;
		}

		self::fail('Project has no statuses.');
	}
}
