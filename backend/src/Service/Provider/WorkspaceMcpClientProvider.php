<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use DateTimeImmutable;
use Kytario\Dto\WorkspaceMcpClientDto;
use Kytario\Model\Entity\OAuthAuthorization;
use Kytario\Model\Entity\Workspace;
use Kytario\Model\Repository\OAuthAuthorizationRepository;
use Kytario\Model\Repository\OAuthClientRepository;
use const DATE_ATOM;

final readonly class WorkspaceMcpClientProvider implements WorkspaceMcpClientProviderInterface
{
	public function __construct(
		private WorkspaceProviderInterface $workspaceProvider,
		private OAuthAuthorizationRepository $oAuthAuthorizationRepository,
		private OAuthClientRepository $oAuthClientRepository,
	) {
	}

	/** @return list<WorkspaceMcpClientDto> */
	public function getClientsForWorkspace(Workspace $workspace): array
	{
		$userIds = [];
		foreach ($this->workspaceProvider->getMembers($workspace) as $membership) {
			$userIds[] = $membership->user->id;
		}

		$now = time();
		/** @var array<string, array{firstSeen: int, lastUsed: int, active: int, total: int}> $byClient */
		$byClient = [];

		foreach ($this->oAuthAuthorizationRepository->findByUserIds($userIds) as $authorization) {
			$createdTs = $authorization->createdAt->getTimestamp();
			$clientId = $authorization->clientId;

			if (!isset($byClient[$clientId])) {
				$byClient[$clientId] = [
					'firstSeen' => $createdTs,
					'lastUsed' => $createdTs,
					'active' => 0,
					'total' => 0,
				];
			}

			$byClient[$clientId]['firstSeen'] = min($byClient[$clientId]['firstSeen'], $createdTs);
			$byClient[$clientId]['lastUsed'] = max($byClient[$clientId]['lastUsed'], $createdTs);
			$byClient[$clientId]['total']++;
			if ($this->isActiveToken($authorization, $now)) {
				$byClient[$clientId]['active']++;
			}
		}

		$result = [];
		foreach ($byClient as $clientId => $stats) {
			$client = $this->oAuthClientRepository->findByClientId($clientId);
			$clientName = $clientId;
			if ($client !== null) {
				$clientName = $client->clientName;
			}

			$result[] = new WorkspaceMcpClientDto(
				clientId: $clientId,
				clientName: $clientName,
				firstSeenAt: date(DATE_ATOM, $stats['firstSeen']),
				lastUsedAt: date(DATE_ATOM, $stats['lastUsed']),
				activeTokens: $stats['active'],
				totalAuthorizations: $stats['total'],
			);
		}

		usort($result, static fn (WorkspaceMcpClientDto $a, WorkspaceMcpClientDto $b): int => strcmp($b->lastUsedAt, $a->lastUsedAt));

		return $result;
	}

	public function revokeClient(Workspace $workspace, string $clientId): int
	{
		$userIds = [];
		foreach ($this->workspaceProvider->getMembers($workspace) as $membership) {
			$userIds[] = $membership->user->id;
		}

		$now = new DateTimeImmutable();
		$revoked = 0;
		foreach ($this->oAuthAuthorizationRepository->findByUserIds($userIds) as $authorization) {
			if ($authorization->clientId !== $clientId || $authorization->revoked) {
				continue;
			}

			$authorization->revoked = true;
			$authorization->updatedAt = $now;
			$this->oAuthAuthorizationRepository->persist($authorization);
			$revoked++;
		}

		return $revoked;
	}

	private function isActiveToken(OAuthAuthorization $authorization, int $now): bool
	{
		if ($authorization->revoked) {
			return false;
		}
		if ($authorization->accessTokenHash === null) {
			return false;
		}
		return $authorization->accessTokenExpires === null || $authorization->accessTokenExpires >= $now;
	}
}
