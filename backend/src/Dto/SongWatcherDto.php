<?php

declare(strict_types=1);

namespace Kytarna\Dto;

use Kytarna\Model\Entity\SongWatcher;

final readonly class SongWatcherDto
{
	public function __construct(public int $userId, public string $userName,)
	{
	}

	public static function fromEntity(SongWatcher $watcher): self
	{
		return new self(userId: $watcher->user->id, userName: $watcher->user->name);
	}
}
