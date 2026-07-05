<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

final class AddScriptRunCallCountsMigration extends Migration
{
	public function up(): void
	{
		$this->table('script_runs')
			->addColumn('http_calls', Type::Int, default: 0)
			->addColumn('task_api_calls', Type::Int, default: 0)
			->alter();
	}

	public function down(): void
	{
		$this->table('script_runs')
			->dropColumn('http_calls')
			->dropColumn('task_api_calls')
			->alter();
	}
}
