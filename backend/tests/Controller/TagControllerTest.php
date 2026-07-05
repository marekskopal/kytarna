<?php

declare(strict_types=1);

namespace Ukolio\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use Ukolio\Controller\TagController;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(TagController::class)]
final class TagControllerTest extends IntegrationTestCase
{
	public function testCreateListUpdateDeleteTag(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);

		$create = $this->request(
			'POST',
			'/api/workspaces/' . $workspace->id . '/tags',
			body: ['name' => 'bug', 'color' => '#ff0000'],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $create->getStatusCode());
		$tagId = self::intField($this->jsonBody($create)['id']);

		$list = $this->request('GET', '/api/workspaces/' . $workspace->id . '/tags', authenticatedAs: $owner);
		self::assertCount(1, $this->jsonList($list));

		$update = $this->request(
			'PUT',
			'/api/workspaces/' . $workspace->id . '/tags/' . $tagId,
			body: ['name' => 'critical', 'color' => '#cc0000'],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $update->getStatusCode());
		self::assertSame('critical', $this->jsonBody($update)['name']);

		$delete = $this->request('DELETE', '/api/workspaces/' . $workspace->id . '/tags/' . $tagId, authenticatedAs: $owner);
		self::assertSame(200, $delete->getStatusCode());

		$listAfter = $this->request('GET', '/api/workspaces/' . $workspace->id . '/tags', authenticatedAs: $owner);
		self::assertCount(0, $this->jsonList($listAfter));
	}

	public function testMemberCannotManageTags(): void
	{
		$owner = Fixture::createUser(email: 'o@example.com');
		$member = Fixture::createUser(email: 'm@example.com');
		$workspace = Fixture::createWorkspace($owner);
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);

		$denied = $this->request(
			'POST',
			'/api/workspaces/' . $workspace->id . '/tags',
			body: ['name' => 'feature', 'color' => '#00ff00'],
			authenticatedAs: $member,
		);
		self::assertSame(401, $denied->getStatusCode());
	}
}
