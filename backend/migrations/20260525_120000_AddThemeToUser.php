<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

final class AddThemeToUserMigration extends Migration
{
	public function up(): void
	{
		$this->table('users')
			->addColumn('theme', Type::Enum, enum: ['system', 'light', 'dark'], default: 'system')
			->alter();
	}

	public function down(): void
	{
		$this->table('users')
			->dropColumn('theme')
			->alter();
	}
}
