<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

final class AddOnboardingCompletedAtToUserMigration extends Migration
{
	public function up(): void
	{
		$this->table('users')
			->addColumn('onboarding_completed_at', Type::Timestamp, nullable: true)
			->alter();
	}

	public function down(): void
	{
		$this->table('users')
			->dropColumn('onboarding_completed_at')
			->alter();
	}
}
