<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

/**
 * Recurring tasks (U-67) add TaskRecurrenceSet/Cleared/Spawned to EventTypeEnum. Extend the
 * events.type ENUM column to match, otherwise recording one of these events truncates to '' under a
 * strict SQL mode (MariaDB STRICT_TRANS_TABLES) and the insert fails.
 */
final class AddTaskRecurrenceEventTypesMigration extends Migration
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
		'TaskArchived',
		'TaskUnarchived',
		'ScriptCreated',
		'ScriptUpdated',
		'ScriptDeleted',
		'ScriptRun',
		'TaskCommentEdited',
		'TaskAssigned',
		'NotificationCreated',
	];

	private const array NEW_TYPES = [
		'TaskRecurrenceSet',
		'TaskRecurrenceCleared',
		'TaskRecurrenceSpawned',
	];

	public function up(): void
	{
		$this->table('events')
			->alterColumn('type', Type::Enum, enum: [...self::PRIOR_TYPES, ...self::NEW_TYPES])
			->alter();
	}

	public function down(): void
	{
		$this->table('events')
			->alterColumn('type', Type::Enum, enum: self::PRIOR_TYPES)
			->alter();
	}
}
