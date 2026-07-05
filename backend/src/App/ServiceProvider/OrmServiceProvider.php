<?php

declare(strict_types=1);

namespace Ukolio\App\ServiceProvider;

use League\Container\Container;
use League\Container\ServiceProvider\AbstractServiceProvider;
use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\ORM;
use MarekSkopal\ORM\Repository\RepositoryInterface;
use Ukolio\Model\Entity\EmailVerificationToken;
use Ukolio\Model\Entity\Event;
use Ukolio\Model\Entity\Field;
use Ukolio\Model\Entity\Invitation;
use Ukolio\Model\Entity\Notification;
use Ukolio\Model\Entity\OAuthAuthorization;
use Ukolio\Model\Entity\OAuthClient;
use Ukolio\Model\Entity\PasswordResetToken;
use Ukolio\Model\Entity\Priority;
use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\ProjectField;
use Ukolio\Model\Entity\SavedView;
use Ukolio\Model\Entity\Script;
use Ukolio\Model\Entity\ScriptRun;
use Ukolio\Model\Entity\ScriptVariable;
use Ukolio\Model\Entity\Status;
use Ukolio\Model\Entity\Tag;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskChecklistItem;
use Ukolio\Model\Entity\TaskComment;
use Ukolio\Model\Entity\TaskFieldValue;
use Ukolio\Model\Entity\TaskFile;
use Ukolio\Model\Entity\TaskRecurrence;
use Ukolio\Model\Entity\TaskRelation;
use Ukolio\Model\Entity\TaskTag;
use Ukolio\Model\Entity\TaskTemplate;
use Ukolio\Model\Entity\TaskWatcher;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workflow;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Entity\WorkspaceUser;
use Ukolio\Model\Repository\EmailVerificationTokenRepository;
use Ukolio\Model\Repository\EventRepository;
use Ukolio\Model\Repository\FieldRepository;
use Ukolio\Model\Repository\InvitationRepository;
use Ukolio\Model\Repository\NotificationRepository;
use Ukolio\Model\Repository\OAuthAuthorizationRepository;
use Ukolio\Model\Repository\OAuthClientRepository;
use Ukolio\Model\Repository\PasswordResetTokenRepository;
use Ukolio\Model\Repository\PriorityRepository;
use Ukolio\Model\Repository\ProjectFieldRepository;
use Ukolio\Model\Repository\ProjectRepository;
use Ukolio\Model\Repository\SavedViewRepository;
use Ukolio\Model\Repository\ScriptRepository;
use Ukolio\Model\Repository\ScriptRunRepository;
use Ukolio\Model\Repository\ScriptVariableRepository;
use Ukolio\Model\Repository\StatusRepository;
use Ukolio\Model\Repository\TagRepository;
use Ukolio\Model\Repository\TaskChecklistItemRepository;
use Ukolio\Model\Repository\TaskCommentRepository;
use Ukolio\Model\Repository\TaskFieldValueRepository;
use Ukolio\Model\Repository\TaskFileRepository;
use Ukolio\Model\Repository\TaskRecurrenceRepository;
use Ukolio\Model\Repository\TaskRelationRepository;
use Ukolio\Model\Repository\TaskRepository;
use Ukolio\Model\Repository\TaskTagRepository;
use Ukolio\Model\Repository\TaskTemplateRepository;
use Ukolio\Model\Repository\TaskWatcherRepository;
use Ukolio\Model\Repository\UserRepository;
use Ukolio\Model\Repository\WorkflowRepository;
use Ukolio\Model\Repository\WorkspaceRepository;
use Ukolio\Model\Repository\WorkspaceUserRepository;
use Ukolio\Service\Dbal\DbContext;

final class OrmServiceProvider extends AbstractServiceProvider
{
	public function __construct(private readonly DbContext $dbContext)
	{
	}

	public function provides(string $id): bool
	{
		return in_array($id, [
			DatabaseInterface::class,
			ORM::class,
			UserRepository::class,
			WorkspaceRepository::class,
			WorkspaceUserRepository::class,
			InvitationRepository::class,
			PasswordResetTokenRepository::class,
			EmailVerificationTokenRepository::class,
			ProjectRepository::class,
			WorkflowRepository::class,
			StatusRepository::class,
			TaskRepository::class,
			TaskChecklistItemRepository::class,
			TaskRecurrenceRepository::class,
			TaskCommentRepository::class,
			TaskFieldValueRepository::class,
			TaskFileRepository::class,
			TaskRelationRepository::class,
			FieldRepository::class,
			ProjectFieldRepository::class,
			TagRepository::class,
			TaskTagRepository::class,
			TaskTemplateRepository::class,
			SavedViewRepository::class,
			PriorityRepository::class,
			EventRepository::class,
			OAuthClientRepository::class,
			OAuthAuthorizationRepository::class,
			ScriptRepository::class,
			ScriptVariableRepository::class,
			ScriptRunRepository::class,
			NotificationRepository::class,
			TaskWatcherRepository::class,
		], true);
	}

	public function register(): void
	{
		$container = $this->getContainer();
		assert($container instanceof Container);

		$container->add(DatabaseInterface::class, fn () => $this->dbContext->getDatabase());
		$container->add(ORM::class, $this->dbContext->getOrm());

		$orm = $this->dbContext->getOrm();

		$this->addRepository($container, $orm, UserRepository::class, User::class);
		$this->addRepository($container, $orm, WorkspaceRepository::class, Workspace::class);
		$this->addRepository($container, $orm, WorkspaceUserRepository::class, WorkspaceUser::class);
		$this->addRepository($container, $orm, InvitationRepository::class, Invitation::class);
		$this->addRepository($container, $orm, PasswordResetTokenRepository::class, PasswordResetToken::class);
		$this->addRepository($container, $orm, EmailVerificationTokenRepository::class, EmailVerificationToken::class);
		$this->addRepository($container, $orm, ProjectRepository::class, Project::class);
		$this->addRepository($container, $orm, WorkflowRepository::class, Workflow::class);
		$this->addRepository($container, $orm, StatusRepository::class, Status::class);
		$this->addRepository($container, $orm, TaskRepository::class, Task::class);
		$this->addRepository($container, $orm, TaskChecklistItemRepository::class, TaskChecklistItem::class);
		$this->addRepository($container, $orm, TaskRecurrenceRepository::class, TaskRecurrence::class);
		$this->addRepository($container, $orm, TaskCommentRepository::class, TaskComment::class);
		$this->addRepository($container, $orm, TaskFieldValueRepository::class, TaskFieldValue::class);
		$this->addRepository($container, $orm, TaskFileRepository::class, TaskFile::class);
		$this->addRepository($container, $orm, TaskRelationRepository::class, TaskRelation::class);
		$this->addRepository($container, $orm, FieldRepository::class, Field::class);
		$this->addRepository($container, $orm, ProjectFieldRepository::class, ProjectField::class);
		$this->addRepository($container, $orm, TagRepository::class, Tag::class);
		$this->addRepository($container, $orm, TaskTagRepository::class, TaskTag::class);
		$this->addRepository($container, $orm, TaskTemplateRepository::class, TaskTemplate::class);
		$this->addRepository($container, $orm, SavedViewRepository::class, SavedView::class);
		$this->addRepository($container, $orm, PriorityRepository::class, Priority::class);
		$this->addRepository($container, $orm, EventRepository::class, Event::class);
		$this->addRepository($container, $orm, OAuthClientRepository::class, OAuthClient::class);
		$this->addRepository($container, $orm, OAuthAuthorizationRepository::class, OAuthAuthorization::class);
		$this->addRepository($container, $orm, ScriptRepository::class, Script::class);
		$this->addRepository($container, $orm, ScriptVariableRepository::class, ScriptVariable::class);
		$this->addRepository($container, $orm, ScriptRunRepository::class, ScriptRun::class);
		$this->addRepository($container, $orm, NotificationRepository::class, Notification::class);
		$this->addRepository($container, $orm, TaskWatcherRepository::class, TaskWatcher::class);
	}

	/**
	 * @param class-string<RepositoryInterface<TEntity>> $repositoryClass
	 * @param class-string<TEntity> $entityClass
	 * @template TEntity of object
	 */
	private function addRepository(Container $container, ORM $orm, string $repositoryClass, string $entityClass): void
	{
		$repository = $orm->getRepository($entityClass);
		$container->add($repositoryClass, fn () => $repository);
	}
}
