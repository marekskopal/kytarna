<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

/**
 * Recurring tasks (U-67). One row per recurring series, attached to the current open "carrier" task.
 * The daily tick (`recurring-tasks:tick`) selects active rows whose `next_run_at` has passed, so the
 * (active, next_run_at) index keeps that query cheap.
 */
final class CreateTaskRecurrencesMigration extends Migration
{
	public function up(): void
	{
		$this->table('task_recurrences')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('task_id', Type::Int, size: 11)
			->addColumn('created_by_id', Type::Int, size: 11)
			->addColumn('cadence', Type::String)
			->addColumn('interval', Type::Int)
			->addColumn('anchor_date', Type::Date)
			->addColumn('end_type', Type::String)
			->addColumn('weekday', Type::Int, nullable: true, default: null)
			->addColumn('day_of_month', Type::Int, nullable: true, default: null)
			->addColumn('cron_expression', Type::String, nullable: true, default: null)
			->addColumn('end_date', Type::Date, nullable: true, default: null)
			->addColumn('max_occurrences', Type::Int, nullable: true, default: null)
			->addColumn('occurrence_count', Type::Int, default: 0)
			->addColumn('next_run_at', Type::Timestamp, nullable: true, default: null)
			->addColumn('last_spawned_at', Type::Timestamp, nullable: true, default: null)
			->addColumn('active', Type::Boolean, default: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['active', 'next_run_at'], 'task_recurrences_active_next_run_index', false)
			->addIndex(['task_id'], 'task_recurrences_task_id_index', false)
			->addForeignKey('task_id', 'tasks', 'id', 'task_recurrences_task_id_fk')
			->addForeignKey('created_by_id', 'users', 'id', 'task_recurrences_created_by_id_fk')
			->create();
	}

	public function down(): void
	{
		$this->table('task_recurrences')->drop();
	}
}
