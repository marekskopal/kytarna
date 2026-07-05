<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use Kytario\Model\Entity\Project;
use Kytario\Model\Entity\ProjectField;
use Kytario\Model\Entity\User;

interface ProjectFieldProviderInterface
{
	/** @return list<ProjectField> */
	public function getProjectFields(Project $project): array;

	/** @param list<int> $fieldIdsInOrder */
	public function setProjectFields(User $author, Project $project, array $fieldIdsInOrder): void;
}
