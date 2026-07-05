<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

/**
 * Task notifications, watchers (U-83). `notifications` is a per-user in-app inbox; `task_watchers`
 * tracks who is subscribed to a task's activity. Foreign keys default to ON DELETE CASCADE
 * (orm-migrations), so deleting a user clears their notifications/watch rows and deleting a task
 * clears its watchers automatically. `notifications.task_id/project_id/actor_id` are plain ints
 * (not FKs) so a notification survives the task it points at — same approach as `events.task_id`.
 */
final class CreateNotificationsAndWatchersMigration extends Migration
{
	public function up(): void
	{
		$this->table('notifications')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('user_id', Type::Int, size: 11)
			->addColumn('workspace_id', Type::Int, size: 11)
			->addColumn(
				'type',
				Type::Enum,
				enum: ['TaskAssigned', 'TaskComment', 'TaskMention', 'TaskMoved', 'DueSoon', 'DueToday'],
			)
			->addColumn('task_id', Type::Int, size: 11, nullable: true, default: null)
			->addColumn('project_id', Type::Int, size: 11, nullable: true, default: null)
			->addColumn('actor_id', Type::Int, size: 11, nullable: true, default: null)
			->addColumn('actor_name', Type::String, nullable: true, default: null)
			->addColumn('data', Type::Text, nullable: true, default: null)
			->addColumn('read_at', Type::Timestamp, nullable: true, default: null)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['user_id', 'read_at'], 'notifications_user_read_index', false)
			->addIndex(['user_id', 'id'], 'notifications_user_id_index', false)
			->addForeignKey('user_id', 'users', 'id', 'notifications_user_id_fk')
			->create();

		$this->table('task_watchers')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('task_id', Type::Int, size: 11)
			->addColumn('user_id', Type::Int, size: 11)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['task_id', 'user_id'], 'task_watchers_task_user_unique', true)
			->addForeignKey('task_id', 'tasks', 'id', 'task_watchers_task_id_fk')
			->addForeignKey('user_id', 'users', 'id', 'task_watchers_user_id_fk')
			->create();
	}

	public function down(): void
	{
		$this->table('task_watchers')->drop();
		$this->table('notifications')->drop();
	}
}
