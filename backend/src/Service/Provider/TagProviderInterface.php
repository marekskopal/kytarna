<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Iterator;
use Kytarna\Model\Entity\Tag;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;

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
