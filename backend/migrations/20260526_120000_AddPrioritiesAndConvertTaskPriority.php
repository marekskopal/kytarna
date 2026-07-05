<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;
use MarekSkopal\ORM\Migrations\Migration\Query\Enum\ReferenceOptionEnum;

final class AddPrioritiesAndConvertTaskPriorityMigration extends Migration
{
	public function up(): void
	{
		$this->table('priorities')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('workspace_id', Type::Int, size: 11)
			->addColumn('name', Type::String)
			->addColumn('color', Type::String, size: 7)
			->addColumn('position', Type::Int)
			->addColumn('is_default', Type::Boolean, default: false)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['workspace_id'], 'priorities_workspace_id_index', false)
			->addIndex(['workspace_id', 'name'], 'priorities_workspace_name_unique', true)
			->addForeignKey('workspace_id', 'workspaces', 'id', 'priorities_workspace_id_fk')
			->create();

		$pdo = $this->databaseProvider->getDatabase()->getPdo();

		// Seed High / Medium / Low for every existing workspace. Position 0 = High (top),
		// 1 = Medium (default), 2 = Low. Colors match frontend/src/styles/_variables.scss light-theme.
		$pdo->exec(
			'INSERT INTO priorities (workspace_id, name, color, position, is_default, created_at, updated_at) '
			. "SELECT id, 'High', '#fdecea', 0, 0, NOW(), NOW() FROM workspaces",
		);
		$pdo->exec(
			'INSERT INTO priorities (workspace_id, name, color, position, is_default, created_at, updated_at) '
			. "SELECT id, 'Medium', '#fbf2dd', 1, 1, NOW(), NOW() FROM workspaces",
		);
		$pdo->exec(
			'INSERT INTO priorities (workspace_id, name, color, position, is_default, created_at, updated_at) '
			. "SELECT id, 'Low', '#f1f1f3', 2, 0, NOW(), NOW() FROM workspaces",
		);

		// Add nullable FK so we can backfill; tighten to NOT NULL after.
		$this->table('tasks')
			->addColumn('priority_id', Type::Int, size: 11, nullable: true)
			->addIndex(['priority_id'], 'tasks_priority_id_index', false)
			->addForeignKey(
				'priority_id',
				'priorities',
				'id',
				'tasks_priority_id_priorities_id_fk',
				onDelete: ReferenceOptionEnum::Restrict,
			)
			->alter();

		// Backfill priority_id from the old enum column. Each task is joined to its workspace
		// via project, then matched to the seeded priority by name.
		$pdo->exec(
			'UPDATE tasks t '
			. 'INNER JOIN projects p ON p.id = t.project_id '
			. 'INNER JOIN priorities pr ON pr.workspace_id = p.workspace_id AND pr.name = t.priority '
			. 'SET t.priority_id = pr.id',
		);

		// Sanity guard: any task without priority_id at this point is data corruption.
		$stmt = $pdo->query('SELECT COUNT(*) FROM tasks WHERE priority_id IS NULL');
		assert($stmt !== false);
		$unmapped = (int) $stmt->fetchColumn();
		if ($unmapped > 0) {
			throw new \RuntimeException(
				'Failed to backfill priority_id on ' . $unmapped . ' task(s). '
				. 'Migration aborted to avoid losing data.',
			);
		}

		$pdo->exec('ALTER TABLE tasks MODIFY priority_id INT NOT NULL');
		$pdo->exec('ALTER TABLE tasks DROP COLUMN priority');
	}

	public function down(): void
	{
		$pdo = $this->databaseProvider->getDatabase()->getPdo();
		$pdo->exec("ALTER TABLE tasks ADD COLUMN priority ENUM('Low', 'Medium', 'High') NOT NULL DEFAULT 'Medium'");
		$pdo->exec('UPDATE tasks t ' . 'INNER JOIN priorities pr ON pr.id = t.priority_id ' . 'SET t.priority = pr.name');

		$this->table('tasks')
			->dropForeignKey('tasks_priority_id_priorities_id_fk')
			->dropIndex('tasks_priority_id_index')
			->dropColumn('priority_id')
			->alter();

		$this->table('priorities')->drop();
	}
}
