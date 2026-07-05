<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity;

use DateTimeImmutable;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;
use Ukolio\Model\Entity\Enum\RecurrenceCadenceEnum;
use Ukolio\Model\Entity\Enum\RecurrenceEndTypeEnum;
use Ukolio\Model\Repository\TaskRecurrenceRepository;

/**
 * A recurrence rule attached to a task (U-67). The task it points at is the current open "carrier" of
 * the series: when an occurrence is spawned (by spawn-on-complete or the daily safety tick), a fresh
 * task is created and the recurrence is re-pointed to it, so exactly one open task carries the series.
 *
 * Scheduling is driven by `nextRunAt`: the tick spawns rows whose `nextRunAt` has passed. `anchorDate`
 * fixes the phase of interval-based presets ("every 2 weeks from this date"); `lastSpawnedAt` is the
 * dedup guard against a completion + tick firing for the same occurrence.
 */
#[Entity(repositoryClass: TaskRecurrenceRepository::class)]
class TaskRecurrence extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Task::class)]
		public Task $task,
		#[ManyToOne(entityClass: User::class, name: 'created_by_id')]
		public User $createdBy,
		#[ColumnEnum(enum: RecurrenceCadenceEnum::class)]
		public RecurrenceCadenceEnum $cadence,
		#[Column(type: Type::Int)]
		public int $interval,
		#[Column(type: Type::Date)]
		public DateTimeImmutable $anchorDate,
		#[ColumnEnum(enum: RecurrenceEndTypeEnum::class)]
		public RecurrenceEndTypeEnum $endType = RecurrenceEndTypeEnum::Never,
		#[Column(type: Type::Int, nullable: true)]
		public ?int $weekday = null,
		#[Column(type: Type::Int, nullable: true)]
		public ?int $dayOfMonth = null,
		#[Column(type: Type::String, nullable: true)]
		public ?string $cronExpression = null,
		#[Column(type: Type::Date, nullable: true)]
		public ?DateTimeImmutable $endDate = null,
		#[Column(type: Type::Int, nullable: true)]
		public ?int $maxOccurrences = null,
		#[Column(type: Type::Int, default: 0)]
		public int $occurrenceCount = 0,
		#[Column(type: Type::Timestamp, nullable: true)]
		public ?DateTimeImmutable $nextRunAt = null,
		#[Column(type: Type::Timestamp, nullable: true)]
		public ?DateTimeImmutable $lastSpawnedAt = null,
		#[Column(type: Type::Boolean, default: true)]
		public bool $active = true,
	) {
	}
}
