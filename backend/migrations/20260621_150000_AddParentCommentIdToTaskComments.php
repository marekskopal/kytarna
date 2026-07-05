<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

/**
 * Threaded comments (U-33): a comment may reply to another comment on the same task.
 * `parent_comment_id` is a nullable self-reference — null marks a top-level comment.
 * The UI clamps threads to a single level (replies always point at a top-level comment).
 */
final class AddParentCommentIdToTaskCommentsMigration extends Migration
{
	public function up(): void
	{
		$this->table('task_comments')
			->addColumn('parent_comment_id', Type::Int, size: 11, nullable: true, default: null)
			->addForeignKey('parent_comment_id', 'task_comments', 'id', 'task_comments_parent_comment_id_fk')
			->alter();
	}

	public function down(): void
	{
		$this->table('task_comments')
			->dropColumn('parent_comment_id')
			->alter();
	}
}
