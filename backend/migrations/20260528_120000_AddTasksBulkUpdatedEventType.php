<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

final class AddTasksBulkUpdatedEventTypeMigration extends Migration
{
	private const array BASE_TYPES = [
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
	];

	public function up(): void
	{
		$this->table('events')
			->alterColumn('type', Type::Enum, enum: [...self::BASE_TYPES, 'TasksBulkUpdated'])
			->alter();
	}

	public function down(): void
	{
		$this->table('events')
			->alterColumn('type', Type::Enum, enum: self::BASE_TYPES)
			->alter();
	}
}
