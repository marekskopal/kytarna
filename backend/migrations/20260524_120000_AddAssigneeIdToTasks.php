<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;
use MarekSkopal\ORM\Migrations\Migration\Query\Enum\ReferenceOptionEnum;

final class AddAssigneeIdToTasksMigration extends Migration
{
	public function up(): void
	{
		$this->table('tasks')
			->addColumn('assignee_id', Type::Int, size: 11, nullable: true)
			->addForeignKey('assignee_id', 'users', 'id', 'tasks_assignee_id_users_id_fk', onDelete: ReferenceOptionEnum::SetNull)
			->alter();
	}

	public function down(): void
	{
		$this->table('tasks')
			->dropForeignKey('assignee_id')
			->dropColumn('assignee_id')
			->alter();
	}
}
