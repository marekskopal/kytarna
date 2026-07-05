<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity;

use DateTimeImmutable;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;
use Ukolio\Model\Entity\Enum\ScriptRunStatusEnum;
use Ukolio\Model\Entity\Enum\ScriptTriggerEnum;
use Ukolio\Model\Repository\ScriptRunRepository;

#[Entity(repositoryClass: ScriptRunRepository::class)]
class ScriptRun extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Script::class)]
		public readonly Script $script,
		#[ColumnEnum(enum: ScriptTriggerEnum::class)]
		public ScriptTriggerEnum $triggerType,
		#[ColumnEnum(enum: ScriptRunStatusEnum::class, default: ScriptRunStatusEnum::Running)]
		public ScriptRunStatusEnum $status = ScriptRunStatusEnum::Running,
		#[Column(type: Type::Timestamp, nullable: true, default: null)]
		public ?DateTimeImmutable $startedAt = null,
		#[Column(type: Type::Timestamp, nullable: true, default: null)]
		public ?DateTimeImmutable $finishedAt = null,
		#[Column(type: Type::Text, nullable: true, default: null)]
		public ?string $logs = null,
		#[Column(type: Type::Text, nullable: true, default: null)]
		public ?string $error = null,
		#[Column(type: Type::Int, default: 0)]
		public int $httpCalls = 0,
		#[Column(type: Type::Int, default: 0)]
		public int $taskApiCalls = 0,
	) {
	}
}
