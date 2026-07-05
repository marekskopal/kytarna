<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

/**
 * Editable comments (U-33): `edited_at` records when a comment's body was last changed.
 * Null means never edited — derived from this rather than comparing created_at/updated_at,
 * which only have second precision and would mis-flag an edit made in the same second.
 */
final class AddEditedAtToTaskCommentsMigration extends Migration
{
	public function up(): void
	{
		$this->table('task_comments')
			->addColumn('edited_at', Type::Timestamp, nullable: true, default: null)
			->alter();
	}

	public function down(): void
	{
		$this->table('task_comments')
			->dropColumn('edited_at')
			->alter();
	}
}
