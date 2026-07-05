<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

/**
 * Lightweight, ordered checklist items embedded in a task (U-69). Distinct from subtasks
 * (which are full Task rows + relations) — these are intra-task steps with optional
 * per-item due date and assignee (cf. Trello's Advanced Checklists).
 */
final class CreateTaskChecklistItemsMigration extends Migration
{
	public function up(): void
	{
		$this->table('task_checklist_items')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('task_id', Type::Int, size: 11)
			->addColumn('text', Type::String, size: 500)
			->addColumn('position', Type::Int)
			->addColumn('checked_at', Type::Timestamp, nullable: true, default: null)
			->addColumn('checked_by_id', Type::Int, size: 11, nullable: true, default: null)
			->addColumn('due_date', Type::Date, nullable: true, default: null)
			->addColumn('assignee_id', Type::Int, size: 11, nullable: true, default: null)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['task_id', 'position'], 'task_checklist_items_task_position_index', false)
			->addForeignKey('task_id', 'tasks', 'id', 'task_checklist_items_task_id_fk')
			->addForeignKey('checked_by_id', 'users', 'id', 'task_checklist_items_checked_by_id_fk')
			->addForeignKey('assignee_id', 'users', 'id', 'task_checklist_items_assignee_id_fk')
			->create();
	}

	public function down(): void
	{
		$this->table('task_checklist_items')->drop();
	}
}
