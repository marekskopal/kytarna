<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

final class AddLoginRateLimitToUserMigration extends Migration
{
	public function up(): void
	{
		$this->table('users')
			->addColumn('failed_login_attempts', Type::Int, default: 0)
			->addColumn('locked_until', Type::Timestamp, nullable: true)
			->alter();
	}

	public function down(): void
	{
		$this->table('users')
			->dropColumn('failed_login_attempts')
			->dropColumn('locked_until')
			->alter();
	}
}
