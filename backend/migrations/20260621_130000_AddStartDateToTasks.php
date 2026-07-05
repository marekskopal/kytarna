<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

/**
 * Tasks gain an optional start_date alongside due_date so the Timeline (Gantt) view can
 * draw bars spanning startDate → dueDate (U-91, prerequisite for U-90).
 */
final class AddStartDateToTasksMigration extends Migration
{
	public function up(): void
	{
		$this->table('tasks')
			->addColumn('start_date', Type::Date, nullable: true)
			->alter();
	}

	public function down(): void
	{
		$this->table('tasks')
			->dropColumn('start_date')
			->alter();
	}
}
