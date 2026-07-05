<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

final class AddDefaultSavedViewIdToUserMigration extends Migration
{
	public function up(): void
	{
		$this->table('users')
			->addColumn('default_saved_view_id', Type::Int, nullable: true, default: null)
			->alter();
	}

	public function down(): void
	{
		$this->table('users')
			->dropColumn('default_saved_view_id')
			->alter();
	}
}
