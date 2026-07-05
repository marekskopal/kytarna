<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use DateTimeImmutable;
use Iterator;
use Kytario\Model\Entity\Enum\EventTypeEnum;
use Kytario\Model\Entity\Project;
use Kytario\Model\Entity\User;
use Kytario\Model\Entity\Workspace;
use Kytario\Model\Repository\ProjectRepository;
use Kytario\Validator\TextFieldValidator;

final readonly class ProjectProvider implements ProjectProviderInterface
{
	public function __construct(
		private ProjectRepository $projectRepository,
		private WorkflowProviderInterface $workflowProvider,
		private EventProviderInterface $eventProvider,
		private ProjectPrefixGeneratorInterface $prefixGenerator,
	) {
	}

	/** @return Iterator<Project> */
	public function getProjects(Workspace $workspace): Iterator
	{
		return $this->projectRepository->findProjectsByWorkspace($workspace->id);
	}

	public function getProject(Workspace $workspace, int $projectId): ?Project
	{
		return $this->projectRepository->findProject($workspace->id, $projectId);
	}

	public function createProject(User $author, Workspace $workspace, string $name, ?string $description): Project
	{
		$name = TextFieldValidator::validateName($name, 'Project');
		$description = TextFieldValidator::validateDescription($description);
		$now = new DateTimeImmutable();
		$prefix = $this->prefixGenerator->generate($workspace, $name, null);
		$project = new Project(workspace: $workspace, name: $name, prefix: $prefix, description: $description);
		$project->createdAt = $now;
		$project->updatedAt = $now;

		$this->projectRepository->persist($project);

		$this->workflowProvider->createDefaultWorkflow($project);

		$this->eventProvider->recordEvent($author, $project, EventTypeEnum::ProjectCreated, ['name' => $name]);

		return $project;
	}

	public function updateProject(User $author, Project $project, string $name, ?string $description): Project
	{
		$name = TextFieldValidator::validateName($name, 'Project');
		$description = TextFieldValidator::validateDescription($description);
		if ($name !== $project->name) {
			$project->prefix = $this->prefixGenerator->generate($project->workspace, $name, $project->id);
		}
		$project->name = $name;
		$project->description = $description;
		$project->updatedAt = new DateTimeImmutable();
		$this->projectRepository->persist($project);

		$this->eventProvider->recordEvent($author, $project, EventTypeEnum::ProjectUpdated, ['name' => $name]);

		return $project;
	}

	public function deleteProject(Project $project): void
	{
		$this->projectRepository->delete($project);
	}
}
