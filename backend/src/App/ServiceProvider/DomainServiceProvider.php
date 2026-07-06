<?php

declare(strict_types=1);

namespace Kytarna\App\ServiceProvider;

use Kytarna\Mcp\McpUserContext;
use Kytarna\Mcp\McpUserContextInterface;
use Kytarna\Mcp\Server\KytarnaServer;
use Kytarna\OAuth\AuthorizationService;
use Kytarna\OAuth\AuthorizationServiceInterface;
use Kytarna\OAuth\ClientService;
use Kytarna\OAuth\ClientServiceInterface;
use Kytarna\Service\Actor\ActorContext;
use Kytarna\Service\Actor\ActorContextInterface;
use Kytarna\Service\Auth\AdminService;
use Kytarna\Service\Auth\AdminServiceInterface;
use Kytarna\Service\Auth\CurrentUserDeletionService;
use Kytarna\Service\Auth\CurrentUserDeletionServiceInterface;
use Kytarna\Service\Auth\PermissionChecker;
use Kytarna\Service\Auth\PermissionCheckerInterface;
use Kytarna\Service\Auth\UserDataExportService;
use Kytarna\Service\Auth\UserDataExportServiceInterface;
use Kytarna\Service\Notification\NotificationDispatcher;
use Kytarna\Service\Notification\NotificationDispatcherInterface;
use Kytarna\Service\Payload\PayloadService;
use Kytarna\Service\Payload\PayloadServiceInterface;
use Kytarna\Service\Provider\BulkLectureProvider;
use Kytarna\Service\Provider\BulkLectureProviderInterface;
use Kytarna\Service\Provider\CoursePrefixGenerator;
use Kytarna\Service\Provider\CoursePrefixGeneratorInterface;
use Kytarna\Service\Provider\CourseProvider;
use Kytarna\Service\Provider\CourseProviderInterface;
use Kytarna\Service\Provider\CourseSequenceProvider;
use Kytarna\Service\Provider\CourseSequenceProviderInterface;
use Kytarna\Service\Provider\EmailVerificationProvider;
use Kytarna\Service\Provider\EmailVerificationProviderInterface;
use Kytarna\Service\Provider\EventProvider;
use Kytarna\Service\Provider\EventProviderInterface;
use Kytarna\Service\Provider\InvitationProvider;
use Kytarna\Service\Provider\InvitationProviderInterface;
use Kytarna\Service\Provider\LectureCodeResolver;
use Kytarna\Service\Provider\LectureCodeResolverInterface;
use Kytarna\Service\Provider\LectureFileProvider;
use Kytarna\Service\Provider\LectureFileProviderInterface;
use Kytarna\Service\Provider\LectureProvider;
use Kytarna\Service\Provider\LectureProviderInterface;
use Kytarna\Service\Provider\LectureTagProvider;
use Kytarna\Service\Provider\LectureTagProviderInterface;
use Kytarna\Service\Provider\LectureWatcherProvider;
use Kytarna\Service\Provider\LectureWatcherProviderInterface;
use Kytarna\Service\Provider\LinkProvider;
use Kytarna\Service\Provider\LinkProviderInterface;
use Kytarna\Service\Provider\NotificationProvider;
use Kytarna\Service\Provider\NotificationProviderInterface;
use Kytarna\Service\Provider\PasswordResetProvider;
use Kytarna\Service\Provider\PasswordResetProviderInterface;
use Kytarna\Service\Provider\ProgressProvider;
use Kytarna\Service\Provider\ProgressProviderInterface;
use Kytarna\Service\Provider\ProgressStatusProvider;
use Kytarna\Service\Provider\ProgressStatusProviderInterface;
use Kytarna\Service\Provider\SavedViewProvider;
use Kytarna\Service\Provider\SavedViewProviderInterface;
use Kytarna\Service\Provider\SongFileProvider;
use Kytarna\Service\Provider\SongFileProviderInterface;
use Kytarna\Service\Provider\SongLinkProvider;
use Kytarna\Service\Provider\SongLinkProviderInterface;
use Kytarna\Service\Provider\SongProgressProvider;
use Kytarna\Service\Provider\SongProgressProviderInterface;
use Kytarna\Service\Provider\SongProvider;
use Kytarna\Service\Provider\SongProviderInterface;
use Kytarna\Service\Provider\SongTabProvider;
use Kytarna\Service\Provider\SongTabProviderInterface;
use Kytarna\Service\Provider\SongTagProvider;
use Kytarna\Service\Provider\SongTagProviderInterface;
use Kytarna\Service\Provider\SongWatcherProvider;
use Kytarna\Service\Provider\SongWatcherProviderInterface;
use Kytarna\Service\Provider\TabProvider;
use Kytarna\Service\Provider\TabProviderInterface;
use Kytarna\Service\Provider\TagProvider;
use Kytarna\Service\Provider\TagProviderInterface;
use Kytarna\Service\Provider\UserProvider;
use Kytarna\Service\Provider\UserProviderInterface;
use Kytarna\Service\Provider\WorkspaceMcpClientProvider;
use Kytarna\Service\Provider\WorkspaceMcpClientProviderInterface;
use Kytarna\Service\Provider\WorkspaceProvider;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Kytarna\Service\Request\RequestService;
use Kytarna\Service\Request\RequestServiceInterface;
use Kytarna\Service\Translator\TranslatorService;
use Kytarna\Service\Translator\TranslatorServiceInterface;
use League\Container\ServiceProvider\AbstractServiceProvider;
use Psr\Log\LoggerInterface;

final class DomainServiceProvider extends AbstractServiceProvider
{
	public function provides(string $id): bool
	{
		return in_array($id, [
			RequestServiceInterface::class,
			UserProviderInterface::class,
			WorkspaceProviderInterface::class,
			WorkspaceMcpClientProviderInterface::class,
			PermissionCheckerInterface::class,
			AdminServiceInterface::class,
			CurrentUserDeletionServiceInterface::class,
			UserDataExportServiceInterface::class,
			InvitationProviderInterface::class,
			PasswordResetProviderInterface::class,
			EmailVerificationProviderInterface::class,
			CourseProviderInterface::class,
			CoursePrefixGeneratorInterface::class,
			CourseSequenceProviderInterface::class,
			LectureProviderInterface::class,
			SongProviderInterface::class,
			SongFileProviderInterface::class,
			SongTabProviderInterface::class,
			SongLinkProviderInterface::class,
			SongProgressProviderInterface::class,
			SongTagProviderInterface::class,
			SongWatcherProviderInterface::class,
			BulkLectureProviderInterface::class,
			LectureCodeResolverInterface::class,
			LectureFileProviderInterface::class,
			TabProviderInterface::class,
			ProgressProviderInterface::class,
			ProgressStatusProviderInterface::class,
			LinkProviderInterface::class,
			TagProviderInterface::class,
			LectureTagProviderInterface::class,
			SavedViewProviderInterface::class,
			EventProviderInterface::class,
			McpUserContextInterface::class,
			ActorContextInterface::class,
			KytarnaServer::class,
			ClientServiceInterface::class,
			AuthorizationServiceInterface::class,
			TranslatorServiceInterface::class,
			PayloadServiceInterface::class,
			LectureWatcherProviderInterface::class,
			NotificationProviderInterface::class,
			NotificationDispatcherInterface::class,
		], true);
	}

	public function register(): void
	{
		$c = $this->getContainer();
		$c->add(RequestServiceInterface::class, RequestService::class);
		$c->add(UserProviderInterface::class, UserProvider::class);
		$c->add(WorkspaceProviderInterface::class, WorkspaceProvider::class);
		$c->add(WorkspaceMcpClientProviderInterface::class, WorkspaceMcpClientProvider::class);
		$c->add(PermissionCheckerInterface::class, PermissionChecker::class);
		$c->add(AdminServiceInterface::class, AdminService::class);
		$c->add(CurrentUserDeletionServiceInterface::class, CurrentUserDeletionService::class);
		$c->add(UserDataExportServiceInterface::class, UserDataExportService::class);
		$c->add(TranslatorServiceInterface::class, static fn (): TranslatorService => new TranslatorService(
			translationsDir: __DIR__ . '/../../../translations',
		));
		$c->add(InvitationProviderInterface::class, InvitationProvider::class);
		$c->add(PasswordResetProviderInterface::class, PasswordResetProvider::class);
		$c->add(EmailVerificationProviderInterface::class, EmailVerificationProvider::class);
		$c->add(PayloadServiceInterface::class, PayloadService::class);
		$c->add(EventProviderInterface::class, EventProvider::class);
		$c->add(CourseProviderInterface::class, CourseProvider::class);
		$c->add(CoursePrefixGeneratorInterface::class, CoursePrefixGenerator::class);
		$c->add(CourseSequenceProviderInterface::class, CourseSequenceProvider::class);
		$c->add(LectureProviderInterface::class, LectureProvider::class);
		$c->add(SongProviderInterface::class, SongProvider::class);
		$c->add(SongFileProviderInterface::class, SongFileProvider::class);
		$c->add(SongTabProviderInterface::class, SongTabProvider::class);
		$c->add(SongLinkProviderInterface::class, SongLinkProvider::class);
		$c->add(SongProgressProviderInterface::class, SongProgressProvider::class);
		$c->add(SongTagProviderInterface::class, SongTagProvider::class);
		$c->add(SongWatcherProviderInterface::class, SongWatcherProvider::class);
		$c->add(BulkLectureProviderInterface::class, BulkLectureProvider::class);
		$c->add(LectureCodeResolverInterface::class, LectureCodeResolver::class);
		$c->add(LectureFileProviderInterface::class, LectureFileProvider::class);
		$c->add(TabProviderInterface::class, TabProvider::class);
		$c->add(ProgressProviderInterface::class, ProgressProvider::class);
		$c->add(ProgressStatusProviderInterface::class, ProgressStatusProvider::class);
		$c->add(LinkProviderInterface::class, LinkProvider::class);
		$c->add(TagProviderInterface::class, TagProvider::class);
		$c->add(LectureTagProviderInterface::class, LectureTagProvider::class);
		$c->add(SavedViewProviderInterface::class, SavedViewProvider::class);
		$c->add(McpUserContextInterface::class, McpUserContext::class);
		$c->add(ActorContextInterface::class, ActorContext::class);
		$c->add(KytarnaServer::class, function () use ($c): KytarnaServer {
			$logger = $c->get(LoggerInterface::class);
			assert($logger instanceof LoggerInterface);
			return new KytarnaServer($c, $logger);
		});
		$c->add(ClientServiceInterface::class, ClientService::class);
		$c->add(AuthorizationServiceInterface::class, AuthorizationService::class);

		$c->add(LectureWatcherProviderInterface::class, LectureWatcherProvider::class);
		$c->add(NotificationProviderInterface::class, NotificationProvider::class);
		$c->add(NotificationDispatcherInterface::class, NotificationDispatcher::class);
	}
}
