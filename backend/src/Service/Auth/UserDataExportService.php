<?php

declare(strict_types=1);

namespace Ukolio\Service\Auth;

use DateTimeImmutable;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\EventRepository;
use Ukolio\Model\Repository\InvitationRepository;
use Ukolio\Model\Repository\OAuthClientRepository;
use Ukolio\Model\Repository\TaskCommentRepository;
use Ukolio\Model\Repository\TaskFileRepository;
use Ukolio\Model\Repository\TaskRelationRepository;
use Ukolio\Model\Repository\WorkspaceUserRepository;
use const DATE_ATOM;
use const JSON_THROW_ON_ERROR;

final readonly class UserDataExportService implements UserDataExportServiceInterface
{
	public function __construct(
		private WorkspaceUserRepository $workspaceUserRepository,
		private InvitationRepository $invitationRepository,
		private EventRepository $eventRepository,
		private TaskCommentRepository $taskCommentRepository,
		private TaskFileRepository $taskFileRepository,
		private TaskRelationRepository $taskRelationRepository,
		private OAuthClientRepository $oAuthClientRepository,
	) {
	}

	/** @return array<string, mixed> */
	public function export(User $user): array
	{
		return [
			'exportedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
			'user' => [
				'id' => $user->id,
				'email' => $user->email,
				'name' => $user->name,
				'locale' => $user->locale->value,
				'theme' => $user->theme->value,
				'systemRole' => $user->systemRole->value,
				'emailVerified' => $user->emailVerified,
				'currentWorkspaceId' => $user->currentWorkspaceId,
				'createdAt' => $user->createdAt->format(DATE_ATOM),
				'updatedAt' => $user->updatedAt->format(DATE_ATOM),
			],
			'workspaceMemberships' => $this->collectMemberships($user->id),
			'invitationsSent' => $this->collectInvitations($user->id),
			'events' => $this->collectEvents($user->id),
			'taskComments' => $this->collectComments($user->id),
			'taskFiles' => $this->collectFiles($user->id),
			'taskRelationsCreated' => $this->collectRelations($user->id),
			'oauthClients' => $this->collectOAuthClients($user->id),
		];
	}

	/** @return list<array<string, mixed>> */
	private function collectMemberships(int $userId): array
	{
		$out = [];
		foreach ($this->workspaceUserRepository->findByUser($userId) as $membership) {
			$out[] = [
				'workspaceId' => $membership->workspace->id,
				'workspaceName' => $membership->workspace->name,
				'role' => $membership->role->value,
				'createdAt' => $membership->createdAt->format(DATE_ATOM),
			];
		}
		return $out;
	}

	/** @return list<array<string, mixed>> */
	private function collectInvitations(int $userId): array
	{
		$out = [];
		foreach ($this->invitationRepository->findByInviter($userId) as $invitation) {
			$out[] = [
				'id' => $invitation->id,
				'workspaceId' => $invitation->workspace->id,
				'email' => $invitation->email,
				'role' => $invitation->role->value,
				'expiresAt' => $invitation->expiresAt->format(DATE_ATOM),
				'acceptedAt' => $invitation->acceptedAt?->format(DATE_ATOM),
				'createdAt' => $invitation->createdAt->format(DATE_ATOM),
			];
		}
		return $out;
	}

	/** @return list<array<string, mixed>> */
	private function collectEvents(int $userId): array
	{
		$out = [];
		foreach ($this->eventRepository->findByAuthor($userId) as $event) {
			/** @var array<string, mixed> $metadata */
			$metadata = json_decode($event->metadata, true, flags: JSON_THROW_ON_ERROR) ?? [];
			$out[] = [
				'id' => $event->id,
				'type' => $event->type->value,
				'metadata' => $metadata,
				'projectId' => $event->project?->id,
				'workspaceId' => $event->workspaceId,
				'taskId' => $event->taskId,
				'actorType' => $event->actorType->value,
				'mcpClientId' => $event->mcpClientId,
				'mcpClientName' => $event->mcpClientName,
				'createdAt' => $event->createdAt->format(DATE_ATOM),
			];
		}
		return $out;
	}

	/** @return list<array<string, mixed>> */
	private function collectComments(int $userId): array
	{
		$out = [];
		foreach ($this->taskCommentRepository->findByAuthor($userId) as $comment) {
			$out[] = [
				'id' => $comment->id,
				'taskId' => $comment->task->id,
				'body' => $comment->body,
				'actorType' => $comment->actorType->value,
				'createdAt' => $comment->createdAt->format(DATE_ATOM),
				'updatedAt' => $comment->updatedAt->format(DATE_ATOM),
			];
		}
		return $out;
	}

	/** @return list<array<string, mixed>> */
	private function collectFiles(int $userId): array
	{
		$out = [];
		foreach ($this->taskFileRepository->findByUploader($userId) as $file) {
			$out[] = [
				'id' => $file->id,
				'taskId' => $file->task->id,
				'filename' => $file->filename,
				'mimeType' => $file->mimeType,
				'size' => $file->size,
				'createdAt' => $file->createdAt->format(DATE_ATOM),
			];
		}
		return $out;
	}

	/** @return list<array<string, mixed>> */
	private function collectRelations(int $userId): array
	{
		$out = [];
		foreach ($this->taskRelationRepository->findByCreatedBy($userId) as $relation) {
			$out[] = [
				'id' => $relation->id,
				'sourceTaskId' => $relation->sourceTask->id,
				'targetTaskId' => $relation->targetTask->id,
				'type' => $relation->type->value,
				'createdAt' => $relation->createdAt->format(DATE_ATOM),
			];
		}
		return $out;
	}

	/** @return list<array<string, mixed>> */
	private function collectOAuthClients(int $userId): array
	{
		$out = [];
		foreach ($this->oAuthClientRepository->findByUser($userId) as $client) {
			$out[] = [
				'id' => $client->id,
				'clientId' => $client->clientId,
				'clientName' => $client->clientName,
				'createdAt' => $client->createdAt->format(DATE_ATOM),
			];
		}
		return $out;
	}
}
