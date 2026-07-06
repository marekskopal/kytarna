<?php

declare(strict_types=1);

namespace Kytarna\Model\Entity\Enum;

enum WorkspaceRoleEnum: string
{
	/** The workspace owner: creates and edits all content, manages members. Exactly one per workspace. */
	case Teacher = 'Teacher';
	/** A learner who joined the workspace: read-only content, tracks their own progress. */
	case Student = 'Student';
}
