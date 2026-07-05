<?php

declare(strict_types=1);

namespace Kytario\Model\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;
use Kytario\Model\Entity\Enum\ActorTypeEnum;
use Kytario\Model\Entity\Enum\EventTypeEnum;
use Kytario\Model\Repository\EventRepository;

#[Entity(repositoryClass: EventRepository::class)]
class Event extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: User::class, nullable: true)]
		public readonly ?User $author,
		#[ColumnEnum(enum: EventTypeEnum::class)]
		public EventTypeEnum $type,
		#[Column(type: Type::Text)]
		public string $metadata,
		#[ManyToOne(entityClass: Project::class, nullable: true)]
		public readonly ?Project $project = null,
		#[Column(type: Type::Int, size: 11, nullable: true)]
		public ?int $workspaceId = null,
		#[Column(type: Type::Int, nullable: true)]
		public ?int $taskId = null,
		#[ColumnEnum(enum: ActorTypeEnum::class, default: ActorTypeEnum::Human)]
		public ActorTypeEnum $actorType = ActorTypeEnum::Human,
		#[Column(type: Type::String, size: 128, nullable: true)]
		public ?string $mcpClientId = null,
		#[Column(type: Type::String, nullable: true)]
		public ?string $mcpClientName = null,
	) {
	}
}
