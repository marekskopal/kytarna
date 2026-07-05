<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

final class CreateScriptsMigration extends Migration
{
	public function up(): void
	{
		$this->table('scripts')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('workspace_id', Type::Int, size: 11)
			->addColumn('created_by_id', Type::Int, size: 11)
			->addColumn('name', Type::String)
			->addColumn('source', Type::Text)
			->addColumn('trigger', Type::Enum, enum: ['Manual', 'Scheduled', 'Event'], default: 'Manual')
			->addColumn('trigger_config', Type::Text, nullable: true, default: null)
			->addColumn('active', Type::Boolean, default: true)
			->addColumn('last_run_at', Type::Timestamp, nullable: true, default: null)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['workspace_id'], 'scripts_workspace_index', false)
			->addIndex(['workspace_id', 'name'], 'scripts_workspace_name_unique', true)
			->addIndex(['trigger', 'active'], 'scripts_trigger_active_index', false)
			->addForeignKey('workspace_id', 'workspaces', 'id', 'scripts_workspace_id_fk')
			->addForeignKey('created_by_id', 'users', 'id', 'scripts_created_by_id_fk')
			->create();

		$this->table('script_variables')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('workspace_id', Type::Int, size: 11)
			->addColumn('key', Type::String)
			->addColumn('value', Type::Text)
			->addColumn('is_secret', Type::Boolean, default: false)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['workspace_id', 'key'], 'script_variables_workspace_key_unique', true)
			->addForeignKey('workspace_id', 'workspaces', 'id', 'script_variables_workspace_id_fk')
			->create();

		$this->table('script_runs')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('script_id', Type::Int, size: 11)
			->addColumn('trigger_type', Type::Enum, enum: ['Manual', 'Scheduled', 'Event'])
			->addColumn('status', Type::Enum, enum: ['Running', 'Success', 'Error', 'Timeout'], default: 'Running')
			->addColumn('started_at', Type::Timestamp, nullable: true, default: null)
			->addColumn('finished_at', Type::Timestamp, nullable: true, default: null)
			->addColumn('logs', Type::Text, nullable: true, default: null)
			->addColumn('error', Type::Text, nullable: true, default: null)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['script_id', 'id'], 'script_runs_script_index', false)
			->addForeignKey('script_id', 'scripts', 'id', 'script_runs_script_id_fk')
			->create();
	}

	public function down(): void
	{
		$this->table('script_runs')->drop();
		$this->table('script_variables')->drop();
		$this->table('scripts')->drop();
	}
}
