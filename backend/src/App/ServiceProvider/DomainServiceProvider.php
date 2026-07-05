<?php

declare(strict_types=1);

namespace Kytario\App\ServiceProvider;

use League\Container\ServiceProvider\AbstractServiceProvider;
use Psr\Log\LoggerInterface;
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
use Kytario\Service\Provider\BulkTaskProvider;
use Kytario\Service\Provider\BulkTaskProviderInterface;
use Kytario\Service\Provider\EmailVerificationProvider;
use Kytario\Service\Provider\EmailVerificationProviderInterface;
use Kytario\Service\Provider\EventProvider;
use Kytario\Service\Provider\EventProviderInterface;
use Kytario\Service\Provider\FieldProvider;
use Kytario\Service\Provider\FieldProviderInterface;
use Kytario\Service\Provider\InvitationProvider;
use Kytario\Service\Provider\InvitationProviderInterface;
use Kytario\Service\Provider\NotificationProvider;
use Kytario\Service\Provider\NotificationProviderInterface;
use Kytario\Service\Provider\PasswordResetProvider;
use Kytario\Service\Provider\PasswordResetProviderInterface;
use Kytario\Service\Provider\PriorityProvider;
use Kytario\Service\Provider\PriorityProviderInterface;
use Kytario\Service\Provider\ProjectFieldProvider;
use Kytario\Service\Provider\ProjectFieldProviderInterface;
use Kytario\Service\Provider\ProjectPrefixGenerator;
use Kytario\Service\Provider\ProjectPrefixGeneratorInterface;
use Kytario\Service\Provider\ProjectProvider;
use Kytario\Service\Provider\ProjectProviderInterface;
use Kytario\Service\Provider\SavedViewProvider;
use Kytario\Service\Provider\SavedViewProviderInterface;
use Kytario\Service\Provider\StatusProvider;
use Kytario\Service\Provider\StatusProviderInterface;
use Kytario\Service\Provider\SubtaskProvider;
use Kytario\Service\Provider\SubtaskProviderInterface;
use Kytario\Service\Provider\TagProvider;
use Kytario\Service\Provider\TagProviderInterface;
use Kytario\Service\Provider\TaskChecklistProvider;
use Kytario\Service\Provider\TaskChecklistProviderInterface;
use Kytario\Service\Provider\TaskCodeResolver;
use Kytario\Service\Provider\TaskCodeResolverInterface;
use Kytario\Service\Provider\TaskCommentProvider;
use Kytario\Service\Provider\TaskCommentProviderInterface;
use Kytario\Service\Provider\TaskFieldValueProvider;
use Kytario\Service\Provider\TaskFieldValueProviderInterface;
use Kytario\Service\Provider\TaskFileProvider;
use Kytario\Service\Provider\TaskFileProviderInterface;
use Kytario\Service\Provider\TaskProvider;
use Kytario\Service\Provider\TaskProviderInterface;
use Kytario\Service\Provider\TaskRecurrenceProvider;
use Kytario\Service\Provider\TaskRecurrenceProviderInterface;
use Kytario\Service\Provider\TaskRelationProvider;
use Kytario\Service\Provider\TaskRelationProviderInterface;
use Kytario\Service\Provider\TaskTagProvider;
use Kytario\Service\Provider\TaskTagProviderInterface;
use Kytario\Service\Provider\TaskTemplateProvider;
use Kytario\Service\Provider\TaskTemplateProviderInterface;
use Kytario\Service\Provider\TaskWatcherProvider;
use Kytario\Service\Provider\TaskWatcherProviderInterface;
use Kytario\Service\Provider\UserProvider;
use Kytario\Service\Provider\UserProviderInterface;
use Kytario\Service\Provider\WorkflowProvider;
use Kytario\Service\Provider\WorkflowProviderInterface;
use Kytario\Service\Provider\WorkspaceMcpClientProvider;
use Kytario\Service\Provider\WorkspaceMcpClientProviderInterface;
use Kytario\Service\Provider\WorkspaceProvider;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use Kytario\Service\Recurrence\RecurrenceTrigger;
use Kytario\Service\Recurrence\RecurrenceTriggerInterface;
use Kytario\Service\Request\RequestService;
use Kytario\Service\Request\RequestServiceInterface;
use Kytario\Service\Script\Engine\ScriptEngineInterface;
use Kytario\Service\Script\Engine\V8JsScriptEngine;
use Kytario\Service\Script\ScriptProvider;
use Kytario\Service\Script\ScriptProviderInterface;
use Kytario\Service\Script\ScriptRunDispatcher;
use Kytario\Service\Script\ScriptRunDispatcherInterface;
use Kytario\Service\Script\ScriptVariableProvider;
use Kytario\Service\Script\ScriptVariableProviderInterface;
use Kytario\Service\Script\SecretCipher;
use Kytario\Service\Script\SecretCipherInterface;
use Kytario\Service\Script\Trigger\CronEvaluator;
use Kytario\Service\Script\Trigger\CronEvaluatorInterface;
use Kytario\Service\Script\Trigger\ScriptEventTrigger;
use Kytario\Service\Script\Trigger\ScriptEventTriggerInterface;
use Kytario\Service\Task\TaskService;
use Kytario\Service\Task\TaskServiceInterface;
use Kytario\Service\Translator\TranslatorService;
use Kytario\Service\Translator\TranslatorServiceInterface;

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
			ProjectProviderInterface::class,
			ProjectPrefixGeneratorInterface::class,
			WorkflowProviderInterface::class,
			StatusProviderInterface::class,
			TaskProviderInterface::class,
			BulkTaskProviderInterface::class,
			TaskCodeResolverInterface::class,
			TaskChecklistProviderInterface::class,
			TaskRecurrenceProviderInterface::class,
			TaskCommentProviderInterface::class,
			TaskFieldValueProviderInterface::class,
			TaskFileProviderInterface::class,
			TaskRelationProviderInterface::class,
			FieldProviderInterface::class,
			ProjectFieldProviderInterface::class,
			TagProviderInterface::class,
			TaskTagProviderInterface::class,
			SubtaskProviderInterface::class,
			TaskTemplateProviderInterface::class,
			SavedViewProviderInterface::class,
			PriorityProviderInterface::class,
			EventProviderInterface::class,
			McpUserContextInterface::class,
			ActorContextInterface::class,
			KytarioServer::class,
			ClientServiceInterface::class,
			AuthorizationServiceInterface::class,
			TranslatorServiceInterface::class,
			TaskServiceInterface::class,
			SecretCipherInterface::class,
			ScriptEngineInterface::class,
			ScriptVariableProviderInterface::class,
			ScriptProviderInterface::class,
			ScriptRunDispatcherInterface::class,
			CronEvaluatorInterface::class,
			ScriptEventTriggerInterface::class,
			TaskWatcherProviderInterface::class,
			NotificationProviderInterface::class,
			NotificationDispatcherInterface::class,
			RecurrenceTriggerInterface::class,
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
		$c->add(TaskServiceInterface::class, TaskService::class);
		$c->add(EventProviderInterface::class, EventProvider::class);
		$c->add(StatusProviderInterface::class, StatusProvider::class);
		$c->add(WorkflowProviderInterface::class, WorkflowProvider::class);
		$c->add(ProjectProviderInterface::class, ProjectProvider::class);
		$c->add(ProjectPrefixGeneratorInterface::class, ProjectPrefixGenerator::class);
		$c->add(TaskProviderInterface::class, TaskProvider::class);
		$c->add(BulkTaskProviderInterface::class, BulkTaskProvider::class);
		$c->add(TaskCodeResolverInterface::class, TaskCodeResolver::class);
		$c->add(TaskChecklistProviderInterface::class, TaskChecklistProvider::class);
		$c->add(TaskRecurrenceProviderInterface::class, TaskRecurrenceProvider::class);
		$c->add(TaskCommentProviderInterface::class, TaskCommentProvider::class);
		$c->add(TaskFieldValueProviderInterface::class, TaskFieldValueProvider::class);
		$c->add(TaskFileProviderInterface::class, TaskFileProvider::class);
		$c->add(TaskRelationProviderInterface::class, TaskRelationProvider::class);
		$c->add(FieldProviderInterface::class, FieldProvider::class);
		$c->add(ProjectFieldProviderInterface::class, ProjectFieldProvider::class);
		$c->add(TagProviderInterface::class, TagProvider::class);
		$c->add(TaskTagProviderInterface::class, TaskTagProvider::class);
		$c->add(SubtaskProviderInterface::class, SubtaskProvider::class);
		$c->add(TaskTemplateProviderInterface::class, TaskTemplateProvider::class);
		$c->add(SavedViewProviderInterface::class, SavedViewProvider::class);
		$c->add(PriorityProviderInterface::class, PriorityProvider::class);
		$c->add(McpUserContextInterface::class, McpUserContext::class);
		$c->add(ActorContextInterface::class, ActorContext::class);
		$c->add(KytarioServer::class, function () use ($c): KytarioServer {
			$logger = $c->get(LoggerInterface::class);
			assert($logger instanceof LoggerInterface);
			return new KytarioServer($c, $logger);
		});
		$c->add(ClientServiceInterface::class, ClientService::class);
		$c->add(AuthorizationServiceInterface::class, AuthorizationService::class);

		$c->add(SecretCipherInterface::class, static fn (): SecretCipher => new SecretCipher((string) getenv('AUTHORIZATION_TOKEN_KEY')));
		$c->add(ScriptEngineInterface::class, V8JsScriptEngine::class);
		$c->add(ScriptVariableProviderInterface::class, ScriptVariableProvider::class);
		$c->add(ScriptProviderInterface::class, ScriptProvider::class);
		$c->add(ScriptRunDispatcherInterface::class, ScriptRunDispatcher::class);
		$c->add(CronEvaluatorInterface::class, CronEvaluator::class);
		$c->add(ScriptEventTriggerInterface::class, ScriptEventTrigger::class);

		$c->add(TaskWatcherProviderInterface::class, TaskWatcherProvider::class);
		$c->add(NotificationProviderInterface::class, NotificationProvider::class);
		$c->add(NotificationDispatcherInterface::class, NotificationDispatcher::class);
		$c->add(RecurrenceTriggerInterface::class, RecurrenceTrigger::class);
	}
}
