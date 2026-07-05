<?php

declare(strict_types=1);

namespace Kytario\Dto;

use Kytario\Model\Entity\LectureWatcher;

final readonly class LectureWatcherDto
{
	public function __construct(public int $userId, public string $userName,)
	{
	}

	public static function fromEntity(LectureWatcher $watcher): self
	{
		return new self(userId: $watcher->user->id, userName: $watcher->user->name);
	}
}
