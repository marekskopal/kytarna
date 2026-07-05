<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

/**
 * Audit-logging for the scripting feature (U-81) adds ScriptCreated / ScriptUpdated /
 * ScriptDeleted / ScriptRun to EventTypeEnum. Extend the events.type ENUM column to match,
 * otherwise recording a script event truncates to '' and the write fails.
 */
final class AddScriptEventTypesMigration extends Migration
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
	];

	private const array SCRIPT_TYPES = ['ScriptCreated', 'ScriptUpdated', 'ScriptDeleted', 'ScriptRun'];

	public function up(): void
	{
		$this->table('events')
			->alterColumn('type', Type::Enum, enum: [...self::PRIOR_TYPES, ...self::SCRIPT_TYPES])
			->alter();
	}

	public function down(): void
	{
		$this->table('events')
			->alterColumn('type', Type::Enum, enum: self::PRIOR_TYPES)
			->alter();
	}
}
