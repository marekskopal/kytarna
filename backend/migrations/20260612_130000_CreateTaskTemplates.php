<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

final class CreateTaskTemplatesMigration extends Migration
{
	public function up(): void
	{
		$this->table('task_templates')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('workspace_id', Type::Int, size: 11)
			->addColumn('name', Type::String)
			->addColumn('payload', Type::Text)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['workspace_id'], 'task_templates_workspace_id_index', false)
			->addIndex(['workspace_id', 'name'], 'task_templates_workspace_name_unique', true)
			->addForeignKey('workspace_id', 'workspaces', 'id', 'task_templates_workspace_id_fk')
			->create();
	}

	public function down(): void
	{
		$this->table('task_templates')->drop();
	}
}
