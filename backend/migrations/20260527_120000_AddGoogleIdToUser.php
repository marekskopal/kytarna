<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

final class AddGoogleIdToUserMigration extends Migration
{
	public function up(): void
	{
		$this->table('users')
			->addColumn('google_id', Type::String, nullable: true, default: null)
			->alterColumn('password', Type::String, nullable: true)
			->alter();
	}

	public function down(): void
	{
		$this->table('users')
			->dropColumn('google_id')
			->alterColumn('password', Type::String, nullable: false)
			->alter();
	}
}
