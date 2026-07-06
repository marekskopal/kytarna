<?php

declare(strict_types=1);

namespace Kytarna\Model\Entity;

use Kytarna\Model\Repository\LectureFileRepository;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;

#[Entity(repositoryClass: LectureFileRepository::class)]
class LectureFile extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Lecture::class)]
		public readonly Lecture $lecture,
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
