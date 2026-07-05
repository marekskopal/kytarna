<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

/**
 * Task archiving (20260620_120000) added the TaskArchived / TaskUnarchived cases to EventTypeEnum
 * but never extended the events.type ENUM column, so recording either event truncated to '' with a
 * "Data truncated for column 'type'" warning and the archive transaction failed. Add the two values.
 */
final class AddTaskArchivedEventTypesMigration extends Migration
{
	private const array PRIOR_TYPES = [
		'ProjectCreated',
		'ProjectUpdated',
		'ProjectDeleted',
		'WorkflowUpdated',
		'StatusCreated',
		'StatusUpdated',
		'StatusDeleted',
		'StatusMoved',
		'TaskCreated',
		'TaskUpdated',
		'TaskDeleted',
		'TaskMoved',
		'MemberRoleChanged',
		'OwnershipTransferred',
		'AdminDeletedWorkspace',
		'AdminDeletedUser',
		'AdminChangedSystemRole',
		'FieldCreated',
		'FieldUpdated',
		'FieldDeleted',
		'ProjectFieldsUpdated',
		'TaskFileAdded',
		'TaskFileDeleted',
		'TaskRelationCreated',
		'TaskRelationDeleted',
		'TagCreated',
		'TagUpdated',
		'TagDeleted',
		'TaskTagsUpdated',
		'TaskCommentAdded',
		'TaskCommentDeleted',
		'UserSelfDeleted',
		'TasksBulkUpdated',
	];

	public function up(): void
	{
		$this->table('events')
			->alterColumn('type', Type::Enum, enum: [...self::PRIOR_TYPES, 'TaskArchived', 'TaskUnarchived'])
			->alter();
	}

	public function down(): void
	{
		$this->table('events')
			->alterColumn('type', Type::Enum, enum: self::PRIOR_TYPES)
			->alter();
	}
}
