<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

final class AddArchivedAtToTasksMigration extends Migration
{
	public function up(): void
	{
		$this->table('tasks')
			->addColumn('archived_at', Type::Timestamp, nullable: true)
			->alter();
	}

	public function down(): void
	{
		$this->table('tasks')
			->dropColumn('archived_at')
			->alter();
	}
}
