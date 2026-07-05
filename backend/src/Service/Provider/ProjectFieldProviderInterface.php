<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\ProjectField;
use Ukolio\Model\Entity\User;

interface ProjectFieldProviderInterface
{
	/** @return list<ProjectField> */
	public function getProjectFields(Project $project): array;

	/** @param list<int> $fieldIdsInOrder */
	public function setProjectFields(User $author, Project $project, array $fieldIdsInOrder): void;
}
