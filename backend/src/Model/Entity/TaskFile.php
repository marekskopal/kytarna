<?php

declare(strict_types=1);

namespace Kytario\Model\Entity;

use Kytario\Model\Repository\TaskFileRepository;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;

#[Entity(repositoryClass: TaskFileRepository::class)]
class TaskFile extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Task::class)]
		public readonly Task $task,
		#[Column(type: Type::String)]
		public string $filename,
		#[Column(type: Type::String)]
		public string $mimeType,
		#[Column(type: Type::Int)]
		public int $size,
		#[Column(type: Type::String, size: 512)]
		public readonly string $storageKey,
		#[ManyToOne(entityClass: User::class, nullable: true, name: 'uploaded_by_user_id')]
		public readonly ?User $uploadedBy,
		#[Column(type: Type::Boolean, default: false)]
		public readonly bool $uploadedByAgent = false,
	) {
	}
}
