<?php

declare(strict_types=1);

namespace Kytario\Model\Entity\Enum;

enum EventTypeEnum: string
{
	case CourseCreated = 'CourseCreated';
	case CourseUpdated = 'CourseUpdated';
	case CourseDeleted = 'CourseDeleted';

	case WorkflowUpdated = 'WorkflowUpdated';

	case StatusCreated = 'StatusCreated';
	case StatusUpdated = 'StatusUpdated';
	case StatusDeleted = 'StatusDeleted';
	case StatusMoved = 'StatusMoved';

	case LectureCreated = 'LectureCreated';
	case LectureUpdated = 'LectureUpdated';
	case LectureDeleted = 'LectureDeleted';
	case LectureMoved = 'LectureMoved';
	case LectureArchived = 'LectureArchived';
	case LectureUnarchived = 'LectureUnarchived';
	case LecturesBulkUpdated = 'LecturesBulkUpdated';

	case MemberRoleChanged = 'MemberRoleChanged';
	case OwnershipTransferred = 'OwnershipTransferred';
	case AdminDeletedWorkspace = 'AdminDeletedWorkspace';
	case AdminDeletedUser = 'AdminDeletedUser';
	case AdminChangedSystemRole = 'AdminChangedSystemRole';

	case LectureFileAdded = 'LectureFileAdded';
	case LectureFileDeleted = 'LectureFileDeleted';

	case TagCreated = 'TagCreated';
	case TagUpdated = 'TagUpdated';
	case TagDeleted = 'TagDeleted';
	case LectureTagsUpdated = 'LectureTagsUpdated';

	case UserSelfDeleted = 'UserSelfDeleted';
}
