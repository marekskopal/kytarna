<?php

declare(strict_types=1);

namespace Kytario\App\ServiceProvider;

use Kytario\Model\Entity\Course;
use Kytario\Model\Entity\EmailVerificationToken;
use Kytario\Model\Entity\Event;
use Kytario\Model\Entity\Invitation;
use Kytario\Model\Entity\Lecture;
use Kytario\Model\Entity\LectureFile;
use Kytario\Model\Entity\LectureLink;
use Kytario\Model\Entity\LectureTag;
use Kytario\Model\Entity\LectureWatcher;
use Kytario\Model\Entity\Notification;
use Kytario\Model\Entity\OAuthAuthorization;
use Kytario\Model\Entity\OAuthClient;
use Kytario\Model\Entity\PasswordResetToken;
use Kytario\Model\Entity\ProgressEntry;
use Kytario\Model\Entity\SavedView;
use Kytario\Model\Entity\Status;
use Kytario\Model\Entity\Tab;
use Kytario\Model\Entity\Tag;
use Kytario\Model\Entity\User;
use Kytario\Model\Entity\Workflow;
use Kytario\Model\Entity\Workspace;
use Kytario\Model\Entity\WorkspaceUser;
use Kytario\Model\Repository\CourseRepository;
use Kytario\Model\Repository\EmailVerificationTokenRepository;
use Kytario\Model\Repository\EventRepository;
use Kytario\Model\Repository\InvitationRepository;
use Kytario\Model\Repository\LectureFileRepository;
use Kytario\Model\Repository\LectureLinkRepository;
use Kytario\Model\Repository\LectureRepository;
use Kytario\Model\Repository\LectureTagRepository;
use Kytario\Model\Repository\LectureWatcherRepository;
use Kytario\Model\Repository\NotificationRepository;
use Kytario\Model\Repository\OAuthAuthorizationRepository;
use Kytario\Model\Repository\OAuthClientRepository;
use Kytario\Model\Repository\PasswordResetTokenRepository;
use Kytario\Model\Repository\ProgressEntryRepository;
use Kytario\Model\Repository\SavedViewRepository;
use Kytario\Model\Repository\StatusRepository;
use Kytario\Model\Repository\TabRepository;
use Kytario\Model\Repository\TagRepository;
use Kytario\Model\Repository\UserRepository;
use Kytario\Model\Repository\WorkflowRepository;
use Kytario\Model\Repository\WorkspaceRepository;
use Kytario\Model\Repository\WorkspaceUserRepository;
use Kytario\Service\Dbal\DbContext;
use League\Container\Container;
use League\Container\ServiceProvider\AbstractServiceProvider;
use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\ORM;
use MarekSkopal\ORM\Repository\RepositoryInterface;

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
			CourseRepository::class,
			WorkflowRepository::class,
			StatusRepository::class,
			LectureRepository::class,
			LectureFileRepository::class,
			TagRepository::class,
			LectureTagRepository::class,
			SavedViewRepository::class,
			EventRepository::class,
			OAuthClientRepository::class,
			OAuthAuthorizationRepository::class,
			NotificationRepository::class,
			LectureWatcherRepository::class,
			TabRepository::class,
			ProgressEntryRepository::class,
			LectureLinkRepository::class,
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
		$this->addRepository($container, $orm, CourseRepository::class, Course::class);
		$this->addRepository($container, $orm, WorkflowRepository::class, Workflow::class);
		$this->addRepository($container, $orm, StatusRepository::class, Status::class);
		$this->addRepository($container, $orm, LectureRepository::class, Lecture::class);
		$this->addRepository($container, $orm, LectureFileRepository::class, LectureFile::class);
		$this->addRepository($container, $orm, TagRepository::class, Tag::class);
		$this->addRepository($container, $orm, LectureTagRepository::class, LectureTag::class);
		$this->addRepository($container, $orm, SavedViewRepository::class, SavedView::class);
		$this->addRepository($container, $orm, EventRepository::class, Event::class);
		$this->addRepository($container, $orm, OAuthClientRepository::class, OAuthClient::class);
		$this->addRepository($container, $orm, OAuthAuthorizationRepository::class, OAuthAuthorization::class);
		$this->addRepository($container, $orm, NotificationRepository::class, Notification::class);
		$this->addRepository($container, $orm, LectureWatcherRepository::class, LectureWatcher::class);
		$this->addRepository($container, $orm, TabRepository::class, Tab::class);
		$this->addRepository($container, $orm, ProgressEntryRepository::class, ProgressEntry::class);
		$this->addRepository($container, $orm, LectureLinkRepository::class, LectureLink::class);
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
