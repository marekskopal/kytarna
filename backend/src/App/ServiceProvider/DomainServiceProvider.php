<?php

declare(strict_types=1);

namespace Kytario\App\ServiceProvider;

use Kytario\Mcp\McpUserContext;
use Kytario\Mcp\McpUserContextInterface;
use Kytario\Mcp\Server\KytarioServer;
use Kytario\OAuth\AuthorizationService;
use Kytario\OAuth\AuthorizationServiceInterface;
use Kytario\OAuth\ClientService;
use Kytario\OAuth\ClientServiceInterface;
use Kytario\Service\Actor\ActorContext;
use Kytario\Service\Actor\ActorContextInterface;
use Kytario\Service\Auth\AdminService;
use Kytario\Service\Auth\AdminServiceInterface;
use Kytario\Service\Auth\CurrentUserDeletionService;
use Kytario\Service\Auth\CurrentUserDeletionServiceInterface;
use Kytario\Service\Auth\PermissionChecker;
use Kytario\Service\Auth\PermissionCheckerInterface;
use Kytario\Service\Auth\UserDataExportService;
use Kytario\Service\Auth\UserDataExportServiceInterface;
use Kytario\Service\Notification\NotificationDispatcher;
use Kytario\Service\Notification\NotificationDispatcherInterface;
use Kytario\Service\Payload\PayloadService;
use Kytario\Service\Payload\PayloadServiceInterface;
use Kytario\Service\Provider\BulkLectureProvider;
use Kytario\Service\Provider\BulkLectureProviderInterface;
use Kytario\Service\Provider\CoursePrefixGenerator;
use Kytario\Service\Provider\CoursePrefixGeneratorInterface;
use Kytario\Service\Provider\CourseProvider;
use Kytario\Service\Provider\CourseProviderInterface;
use Kytario\Service\Provider\EmailVerificationProvider;
use Kytario\Service\Provider\EmailVerificationProviderInterface;
use Kytario\Service\Provider\EventProvider;
use Kytario\Service\Provider\EventProviderInterface;
use Kytario\Service\Provider\InvitationProvider;
use Kytario\Service\Provider\InvitationProviderInterface;
use Kytario\Service\Provider\LectureCodeResolver;
use Kytario\Service\Provider\LectureCodeResolverInterface;
use Kytario\Service\Provider\LectureFileProvider;
use Kytario\Service\Provider\LectureFileProviderInterface;
use Kytario\Service\Provider\LectureProvider;
use Kytario\Service\Provider\LectureProviderInterface;
use Kytario\Service\Provider\LectureTagProvider;
use Kytario\Service\Provider\LectureTagProviderInterface;
use Kytario\Service\Provider\LectureWatcherProvider;
use Kytario\Service\Provider\LectureWatcherProviderInterface;
use Kytario\Service\Provider\LinkProvider;
use Kytario\Service\Provider\LinkProviderInterface;
use Kytario\Service\Provider\NotificationProvider;
use Kytario\Service\Provider\NotificationProviderInterface;
use Kytario\Service\Provider\PasswordResetProvider;
use Kytario\Service\Provider\PasswordResetProviderInterface;
use Kytario\Service\Provider\ProgressProvider;
use Kytario\Service\Provider\ProgressProviderInterface;
use Kytario\Service\Provider\SavedViewProvider;
use Kytario\Service\Provider\SavedViewProviderInterface;
use Kytario\Service\Provider\StatusProvider;
use Kytario\Service\Provider\StatusProviderInterface;
use Kytario\Service\Provider\TabProvider;
use Kytario\Service\Provider\TabProviderInterface;
use Kytario\Service\Provider\TagProvider;
use Kytario\Service\Provider\TagProviderInterface;
use Kytario\Service\Provider\UserProvider;
use Kytario\Service\Provider\UserProviderInterface;
use Kytario\Service\Provider\WorkflowProvider;
use Kytario\Service\Provider\WorkflowProviderInterface;
use Kytario\Service\Provider\WorkspaceMcpClientProvider;
use Kytario\Service\Provider\WorkspaceMcpClientProviderInterface;
use Kytario\Service\Provider\WorkspaceProvider;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use Kytario\Service\Request\RequestService;
use Kytario\Service\Request\RequestServiceInterface;
use Kytario\Service\Translator\TranslatorService;
use Kytario\Service\Translator\TranslatorServiceInterface;
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
			WorkflowProviderInterface::class,
			StatusProviderInterface::class,
			LectureProviderInterface::class,
			BulkLectureProviderInterface::class,
			LectureCodeResolverInterface::class,
			LectureFileProviderInterface::class,
			TabProviderInterface::class,
			ProgressProviderInterface::class,
			LinkProviderInterface::class,
			TagProviderInterface::class,
			LectureTagProviderInterface::class,
			SavedViewProviderInterface::class,
			EventProviderInterface::class,
			McpUserContextInterface::class,
			ActorContextInterface::class,
			KytarioServer::class,
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
		$c->add(StatusProviderInterface::class, StatusProvider::class);
		$c->add(WorkflowProviderInterface::class, WorkflowProvider::class);
		$c->add(CourseProviderInterface::class, CourseProvider::class);
		$c->add(CoursePrefixGeneratorInterface::class, CoursePrefixGenerator::class);
		$c->add(LectureProviderInterface::class, LectureProvider::class);
		$c->add(BulkLectureProviderInterface::class, BulkLectureProvider::class);
		$c->add(LectureCodeResolverInterface::class, LectureCodeResolver::class);
		$c->add(LectureFileProviderInterface::class, LectureFileProvider::class);
		$c->add(TabProviderInterface::class, TabProvider::class);
		$c->add(ProgressProviderInterface::class, ProgressProvider::class);
		$c->add(LinkProviderInterface::class, LinkProvider::class);
		$c->add(TagProviderInterface::class, TagProvider::class);
		$c->add(LectureTagProviderInterface::class, LectureTagProvider::class);
		$c->add(SavedViewProviderInterface::class, SavedViewProvider::class);
		$c->add(McpUserContextInterface::class, McpUserContext::class);
		$c->add(ActorContextInterface::class, ActorContext::class);
		$c->add(KytarioServer::class, function () use ($c): KytarioServer {
			$logger = $c->get(LoggerInterface::class);
			assert($logger instanceof LoggerInterface);
			return new KytarioServer($c, $logger);
		});
		$c->add(ClientServiceInterface::class, ClientService::class);
		$c->add(AuthorizationServiceInterface::class, AuthorizationService::class);

		$c->add(LectureWatcherProviderInterface::class, LectureWatcherProvider::class);
		$c->add(NotificationProviderInterface::class, NotificationProvider::class);
		$c->add(NotificationDispatcherInterface::class, NotificationDispatcher::class);
	}
}
