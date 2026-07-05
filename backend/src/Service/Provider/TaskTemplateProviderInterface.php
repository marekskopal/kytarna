<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use Iterator;
use Kytario\Model\Entity\Project;
use Kytario\Model\Entity\Status;
use Kytario\Model\Entity\Task;
use Kytario\Model\Entity\TaskTemplate;
use Kytario\Model\Entity\User;
use Kytario\Model\Entity\Workspace;

interface TaskTemplateProviderInterface
{
	/** @return Iterator<TaskTemplate> */
	public function getTemplates(Workspace $workspace): Iterator;

	public function getTemplate(Workspace $workspace, int $templateId): ?TaskTemplate;

	public function getTemplateById(int $templateId): ?TaskTemplate;

	public function createFromTask(Task $task, string $name): TaskTemplate;

	public function deleteTemplate(TaskTemplate $template): void;

	public function instantiate(User $author, TaskTemplate $template, Project $project, Status $status, ?string $name = null): Task;
}
