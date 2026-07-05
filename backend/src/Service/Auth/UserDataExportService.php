<?php

declare(strict_types=1);

namespace Kytario\Service\Auth;

use DateTimeImmutable;
use Kytario\Model\Entity\User;
use Kytario\Model\Repository\EventRepository;
use Kytario\Model\Repository\InvitationRepository;
use Kytario\Model\Repository\OAuthClientRepository;
use Kytario\Model\Repository\TaskFileRepository;
use Kytario\Model\Repository\WorkspaceUserRepository;
use const DATE_ATOM;
use const JSON_THROW_ON_ERROR;

final readonly class UserDataExportService implements UserDataExportServiceInterface
{
	public function __construct(
		private WorkspaceUserRepository $workspaceUserRepository,
		private InvitationRepository $invitationRepository,
		private EventRepository $eventRepository,
		private TaskFileRepository $taskFileRepository,
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
			'taskFiles' => $this->collectFiles($user->id),
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
