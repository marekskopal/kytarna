<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Iterator;
use Ukolio\Model\Entity\Tag;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;

interface TagProviderInterface
{
	/** @return Iterator<Tag> */
	public function getTags(Workspace $workspace): Iterator;

	public function getTag(Workspace $workspace, int $tagId): ?Tag;

	public function findTagByName(Workspace $workspace, string $name): ?Tag;

	public function createTag(User $author, Workspace $workspace, string $name, string $color): Tag;

	public function updateTag(User $author, Tag $tag, string $name, string $color): Tag;

	public function deleteTag(User $author, Tag $tag): void;
}
