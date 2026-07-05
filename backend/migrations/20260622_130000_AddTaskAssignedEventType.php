<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

/**
 * Notifications (U-83) add TaskAssigned (a dedicated assignment event the notification fan-out
 * reacts to) and the realtime-only NotificationCreated signal to EventTypeEnum. Extend the
 * events.type ENUM column to match, otherwise recording a TaskAssigned event truncates to '' and
 * subsequent reads fail. NotificationCreated is never persisted as an event row but is included so
 * the column stays in sync with the PHP enum.
 */
final class AddTaskAssignedEventTypeMigration extends Migration
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
	];

	public function up(): void
	{
		$this->table('events')
			->alterColumn('type', Type::Enum, enum: [...self::PRIOR_TYPES, 'TaskAssigned', 'NotificationCreated'])
			->alter();
	}

	public function down(): void
	{
		$this->table('events')
			->alterColumn('type', Type::Enum, enum: self::PRIOR_TYPES)
			->alter();
	}
}
