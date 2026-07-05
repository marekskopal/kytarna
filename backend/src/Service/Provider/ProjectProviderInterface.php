<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Iterator;
use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;

interface ProjectProviderInterface
{
	/** @return Iterator<Project> */
	public function getProjects(Workspace $workspace): Iterator;

	public function getProject(Workspace $workspace, int $projectId): ?Project;

	public function createProject(User $author, Workspace $workspace, string $name, ?string $description): Project;

	public function updateProject(User $author, Project $project, string $name, ?string $description): Project;

	public function deleteProject(Project $project): void;
}
