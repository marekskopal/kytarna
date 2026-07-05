<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity\Enum;

enum EventTypeEnum: string
{
	case ProjectCreated = 'ProjectCreated';
	case ProjectUpdated = 'ProjectUpdated';
	case ProjectDeleted = 'ProjectDeleted';

	case WorkflowUpdated = 'WorkflowUpdated';

	case StatusCreated = 'StatusCreated';
	case StatusUpdated = 'StatusUpdated';
	case StatusDeleted = 'StatusDeleted';
	case StatusMoved = 'StatusMoved';

	case TaskCreated = 'TaskCreated';
	case TaskUpdated = 'TaskUpdated';
	case TaskAssigned = 'TaskAssigned';
	case TaskDeleted = 'TaskDeleted';
	case TaskMoved = 'TaskMoved';
	case TaskArchived = 'TaskArchived';
	case TaskUnarchived = 'TaskUnarchived';
	case TasksBulkUpdated = 'TasksBulkUpdated';
	case TaskRecurrenceSet = 'TaskRecurrenceSet';
	case TaskRecurrenceCleared = 'TaskRecurrenceCleared';
	case TaskRecurrenceSpawned = 'TaskRecurrenceSpawned';

	case MemberRoleChanged = 'MemberRoleChanged';
	case OwnershipTransferred = 'OwnershipTransferred';
	case AdminDeletedWorkspace = 'AdminDeletedWorkspace';
	case AdminDeletedUser = 'AdminDeletedUser';
	case AdminChangedSystemRole = 'AdminChangedSystemRole';

	case FieldCreated = 'FieldCreated';
	case FieldUpdated = 'FieldUpdated';
	case FieldDeleted = 'FieldDeleted';
	case ProjectFieldsUpdated = 'ProjectFieldsUpdated';

	case TaskFileAdded = 'TaskFileAdded';
	case TaskFileDeleted = 'TaskFileDeleted';

	case TaskRelationCreated = 'TaskRelationCreated';
	case TaskRelationDeleted = 'TaskRelationDeleted';

	case TagCreated = 'TagCreated';
	case TagUpdated = 'TagUpdated';
	case TagDeleted = 'TagDeleted';
	case TaskTagsUpdated = 'TaskTagsUpdated';

	case TaskCommentAdded = 'TaskCommentAdded';
	case TaskCommentEdited = 'TaskCommentEdited';
	case TaskCommentDeleted = 'TaskCommentDeleted';

	case ScriptCreated = 'ScriptCreated';
	case ScriptUpdated = 'ScriptUpdated';
	case ScriptDeleted = 'ScriptDeleted';
	case ScriptRun = 'ScriptRun';

	case UserSelfDeleted = 'UserSelfDeleted';

	// Realtime-only signal — published to the Mercure stream so a recipient's bell refreshes.
	// Never persisted as an Event row (notifications are not audit events).
	case NotificationCreated = 'NotificationCreated';
}
