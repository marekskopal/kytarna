<?php

declare(strict_types=1);

namespace Kytarna\App\ServiceProvider;

use Kytarna\Model\Entity\Course;
use Kytarna\Model\Entity\EmailVerificationToken;
use Kytarna\Model\Entity\Event;
use Kytarna\Model\Entity\Invitation;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\LectureFile;
use Kytarna\Model\Entity\LectureLink;
use Kytarna\Model\Entity\LectureTag;
use Kytarna\Model\Entity\LectureWatcher;
use Kytarna\Model\Entity\Notification;
use Kytarna\Model\Entity\OAuthAuthorization;
use Kytarna\Model\Entity\OAuthClient;
use Kytarna\Model\Entity\PasswordResetToken;
use Kytarna\Model\Entity\ProgressEntry;
use Kytarna\Model\Entity\SavedView;
use Kytarna\Model\Entity\Status;
use Kytarna\Model\Entity\Tab;
use Kytarna\Model\Entity\Tag;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workflow;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Model\Entity\WorkspaceUser;
use Kytarna\Model\Repository\CourseRepository;
use Kytarna\Model\Repository\EmailVerificationTokenRepository;
use Kytarna\Model\Repository\EventRepository;
use Kytarna\Model\Repository\InvitationRepository;
use Kytarna\Model\Repository\LectureFileRepository;
use Kytarna\Model\Repository\LectureLinkRepository;
use Kytarna\Model\Repository\LectureRepository;
use Kytarna\Model\Repository\LectureTagRepository;
use Kytarna\Model\Repository\LectureWatcherRepository;
use Kytarna\Model\Repository\NotificationRepository;
use Kytarna\Model\Repository\OAuthAuthorizationRepository;
use Kytarna\Model\Repository\OAuthClientRepository;
use Kytarna\Model\Repository\PasswordResetTokenRepository;
use Kytarna\Model\Repository\ProgressEntryRepository;
use Kytarna\Model\Repository\SavedViewRepository;
use Kytarna\Model\Repository\StatusRepository;
use Kytarna\Model\Repository\TabRepository;
use Kytarna\Model\Repository\TagRepository;
use Kytarna\Model\Repository\UserRepository;
use Kytarna\Model\Repository\WorkflowRepository;
use Kytarna\Model\Repository\WorkspaceRepository;
use Kytarna\Model\Repository\WorkspaceUserRepository;
use Kytarna\Service\Dbal\DbContext;
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
