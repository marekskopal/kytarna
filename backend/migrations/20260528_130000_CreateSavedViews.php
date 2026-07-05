<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

final class CreateSavedViewsMigration extends Migration
{
	public function up(): void
	{
		$this->table('saved_views')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('workspace_id', Type::Int, size: 11)
			->addColumn('user_id', Type::Int, size: 11)
			->addColumn('name', Type::String)
			->addColumn('filter_config', Type::Text)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['workspace_id', 'user_id'], 'saved_views_workspace_user_index', false)
			->addIndex(['workspace_id', 'user_id', 'name'], 'saved_views_workspace_user_name_unique', true)
			->addForeignKey('workspace_id', 'workspaces', 'id', 'saved_views_workspace_id_fk')
			->addForeignKey('user_id', 'users', 'id', 'saved_views_user_id_fk')
			->create();
	}

	public function down(): void
	{
		$this->table('saved_views')->drop();
	}
}
