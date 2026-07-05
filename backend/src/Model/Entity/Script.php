<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity;

use DateTimeImmutable;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;
use Ukolio\Model\Entity\Enum\ScriptTriggerEnum;
use Ukolio\Model\Repository\ScriptRepository;

#[Entity(repositoryClass: ScriptRepository::class)]
class Script extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Workspace::class)]
		public readonly Workspace $workspace,
		#[ManyToOne(entityClass: User::class)]
		public readonly User $createdBy,
		#[Column(type: Type::String)]
		public string $name,
		#[Column(type: Type::Text)]
		public string $source,
		#[ColumnEnum(enum: ScriptTriggerEnum::class, default: ScriptTriggerEnum::Manual)]
		public ScriptTriggerEnum $trigger = ScriptTriggerEnum::Manual,
		/** Cron expression for Scheduled, or JSON list of Event type names for Event triggers; null for Manual. */
		#[Column(type: Type::Text, nullable: true, default: null)]
		public ?string $triggerConfig = null,
		#[Column(type: Type::Boolean, default: true)]
		public bool $active = true,
		#[Column(type: Type::Timestamp, nullable: true, default: null)]
		public ?DateTimeImmutable $lastRunAt = null,
	) {
	}
}
