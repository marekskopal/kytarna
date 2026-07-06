<?php

declare(strict_types=1);

namespace Kytarna\Model\Entity\Enum;

enum EventTypeEnum: string
{
	case CourseCreated = 'CourseCreated';
	case CourseUpdated = 'CourseUpdated';
	case CourseDeleted = 'CourseDeleted';

	case LectureCreated = 'LectureCreated';
	case LectureUpdated = 'LectureUpdated';
	case LectureDeleted = 'LectureDeleted';
	case LectureMoved = 'LectureMoved';
	case LectureArchived = 'LectureArchived';
	case LectureUnarchived = 'LectureUnarchived';
	case LecturesBulkUpdated = 'LecturesBulkUpdated';

	case SongCreated = 'SongCreated';
	case SongUpdated = 'SongUpdated';
	case SongDeleted = 'SongDeleted';
	case SongMoved = 'SongMoved';
	case SongArchived = 'SongArchived';
	case SongUnarchived = 'SongUnarchived';
	case SongAddedToCourse = 'SongAddedToCourse';
	case SongRemovedFromCourse = 'SongRemovedFromCourse';
	case SongFileAdded = 'SongFileAdded';
	case SongFileDeleted = 'SongFileDeleted';
	case SongTagsUpdated = 'SongTagsUpdated';

	case MemberJoined = 'MemberJoined';
	case MemberLeft = 'MemberLeft';
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
