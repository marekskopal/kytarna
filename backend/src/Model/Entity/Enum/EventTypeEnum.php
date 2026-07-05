<?php

declare(strict_types=1);

namespace Kytario\Model\Entity\Enum;

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

	case MemberRoleChanged = 'MemberRoleChanged';
	case OwnershipTransferred = 'OwnershipTransferred';
	case AdminDeletedWorkspace = 'AdminDeletedWorkspace';
	case AdminDeletedUser = 'AdminDeletedUser';
	case AdminChangedSystemRole = 'AdminChangedSystemRole';

	case TaskFileAdded = 'TaskFileAdded';
	case TaskFileDeleted = 'TaskFileDeleted';

	case TagCreated = 'TagCreated';
	case TagUpdated = 'TagUpdated';
	case TagDeleted = 'TagDeleted';
	case TaskTagsUpdated = 'TaskTagsUpdated';

	case UserSelfDeleted = 'UserSelfDeleted';
}
