<?php

declare(strict_types=1);

namespace Kytario\Route;

enum Routes: string
{
	case Health = '/api/health';

	case AuthenticationLogin = '/api/authentication/login';
	case AuthenticationLogout = '/api/authentication/logout';
	case AuthenticationSignUp = '/api/authentication/sign-up';
	case AuthenticationRefreshToken = '/api/authentication/refresh-token';
	case AuthenticationRequestPasswordReset = '/api/authentication/request-password-reset';
	case AuthenticationConfirmPasswordReset = '/api/authentication/confirm-password-reset';
	case AuthenticationVerifyEmail = '/api/authentication/verify-email';
	case AuthenticationGoogleClientId = '/api/authentication/google-client-id';
	case AuthenticationGoogleLogin = '/api/authentication/google-login';

	case CurrentUser = '/api/current-user';
	case CurrentUserPassword = '/api/current-user/password';
	case CurrentUserResendVerification = '/api/current-user/resend-verification';
	case CurrentUserExport = '/api/current-user/export';
	case CurrentUserOnboardingComplete = '/api/current-user/onboarding-complete';

	case Workspaces = '/api/workspaces';
	case Workspace = '/api/workspaces/{workspaceId:number}';
	case WorkspaceSwitch = '/api/workspaces/{workspaceId:number}/switch';
	case WorkspaceMembers = '/api/workspaces/{workspaceId:number}/members';
	case WorkspaceMember = '/api/workspaces/{workspaceId:number}/members/{userId:number}';
	case WorkspaceTransferOwnership = '/api/workspaces/{workspaceId:number}/transfer-ownership';
	case WorkspaceInvitations = '/api/workspaces/{workspaceId:number}/invitations';
	case WorkspaceFields = '/api/workspaces/{workspaceId:number}/fields';
	case WorkspaceField = '/api/workspaces/{workspaceId:number}/fields/{fieldId:number}';
	case WorkspaceTags = '/api/workspaces/{workspaceId:number}/tags';
	case WorkspaceTag = '/api/workspaces/{workspaceId:number}/tags/{tagId:number}';
	case WorkspacePriorities = '/api/workspaces/{workspaceId:number}/priorities';
	case WorkspacePriority = '/api/workspaces/{workspaceId:number}/priorities/{priorityId:number}';
	case PriorityMove = '/api/workspaces/{workspaceId:number}/priorities/{priorityId:number}/move';
	case WorkspaceMcpClients = '/api/workspaces/{workspaceId:number}/mcp-clients';
	case WorkspaceMcpClientRevoke = '/api/workspaces/{workspaceId:number}/mcp-clients/{clientId:[a-f0-9]+}/revoke';
	case WorkspaceEvents = '/api/workspaces/{workspaceId:number}/events';
	case WorkspaceAgentStats = '/api/workspaces/{workspaceId:number}/agent-stats';
	case Invitation = '/api/invitations/{invitationId:number}';
	case InvitationLookup = '/api/invitations/lookup';
	case InvitationAccept = '/api/invitations/accept';

	case Projects = '/api/projects';
	case Project = '/api/projects/{projectId:number}';
	case ProjectBoard = '/api/projects/{projectId:number}/board';
	case ProjectEvents = '/api/projects/{projectId:number}/events';
	case ProjectWorkflow = '/api/projects/{projectId:number}/workflow';
	case ProjectTasks = '/api/projects/{projectId:number}/tasks';
	case ProjectFields = '/api/projects/{projectId:number}/fields';

	case Workflows = '/api/workflows';
	case WorkflowStatuses = '/api/workflows/{workflowId:number}/statuses';
	case Status = '/api/statuses/{statusId:number}';
	case StatusMove = '/api/statuses/{statusId:number}/move';

	case Tasks = '/api/tasks';
	case TasksBulk = '/api/tasks/bulk';
	// taskId pattern accepts numeric IDs and project-prefixed codes (uppercase + dash, e.g. MP-3).
	// Lowercase is intentionally excluded so static sibling paths like /api/tasks/bulk don't collide.
	case Task = '/api/tasks/{taskId:[A-Z0-9][A-Z0-9-]*}';
	case TaskMove = '/api/tasks/{taskId:[A-Z0-9][A-Z0-9-]*}/move';
	case TaskArchive = '/api/tasks/{taskId:[A-Z0-9][A-Z0-9-]*}/archive';
	case TaskUnarchive = '/api/tasks/{taskId:[A-Z0-9][A-Z0-9-]*}/unarchive';
	case TaskDuplicate = '/api/tasks/{taskId:[A-Z0-9][A-Z0-9-]*}/duplicate';
	case TaskSaveAsTemplate = '/api/tasks/{taskId:[A-Z0-9][A-Z0-9-]*}/save-as-template';
	case TaskFiles = '/api/tasks/{taskId:[A-Z0-9][A-Z0-9-]*}/files';
	case TaskFile = '/api/tasks/{taskId:[A-Z0-9][A-Z0-9-]*}/files/{fileId:number}';
	case TaskFileContent = '/api/tasks/{taskId:[A-Z0-9][A-Z0-9-]*}/files/{fileId:number}/content';
	case TaskRelations = '/api/tasks/{taskId:[A-Z0-9][A-Z0-9-]*}/relations';
	case TaskSubtasks = '/api/tasks/{taskId:[A-Z0-9][A-Z0-9-]*}/subtasks';
	case TaskRelation = '/api/task-relations/{relationId:number}';
	case TaskComments = '/api/tasks/{taskId:[A-Z0-9][A-Z0-9-]*}/comments';
	case TaskComment = '/api/task-comments/{commentId:number}';
	case TaskChecklist = '/api/tasks/{taskId:[A-Z0-9][A-Z0-9-]*}/checklist';
	case TaskChecklistItem = '/api/checklist-items/{itemId:number}';
	case TaskChecklistItemMove = '/api/checklist-items/{itemId:number}/move';
	case TaskWatchers = '/api/tasks/{taskId:[A-Z0-9][A-Z0-9-]*}/watchers';
	case TaskWatch = '/api/tasks/{taskId:[A-Z0-9][A-Z0-9-]*}/watch';
	case TaskRecurrence = '/api/tasks/{taskId:[A-Z0-9][A-Z0-9-]*}/recurrence';

	case Notifications = '/api/notifications';
	case NotificationsReadAll = '/api/notifications/read-all';
	case NotificationsUnreadCount = '/api/notifications/unread-count';
	case NotificationRead = '/api/notifications/{notificationId:number}/read';
	case Notification = '/api/notifications/{notificationId:number}';

	case Search = '/api/search';

	case WorkspaceSavedViews = '/api/workspaces/{workspaceId:number}/saved-views';
	case SavedView = '/api/saved-views/{savedViewId:number}';

	case WorkspaceTaskTemplates = '/api/workspaces/{workspaceId:number}/task-templates';
	case TaskTemplate = '/api/task-templates/{taskTemplateId:number}';

	case WorkspaceScripts = '/api/workspaces/{workspaceId:number}/scripts';
	case Script = '/api/scripts/{scriptId:number}';
	case ScriptRunNow = '/api/scripts/{scriptId:number}/run';
	case ScriptRuns = '/api/scripts/{scriptId:number}/runs';
	case WorkspaceScriptVariables = '/api/workspaces/{workspaceId:number}/script-variables';
	case WorkspaceScriptVariable = '/api/workspaces/{workspaceId:number}/script-variables/{variableId:number}';

	case AdminUsers = '/api/admin/users';
	case AdminUser = '/api/admin/users/{userId:number}';
	case AdminWorkspaces = '/api/admin/workspaces';
	case AdminWorkspace = '/api/admin/workspaces/{workspaceId:number}';
	case AdminWorkspaceMembers = '/api/admin/workspaces/{workspaceId:number}/members';
	case AdminWorkspaceMember = '/api/admin/workspaces/{workspaceId:number}/members/{userId:number}';
	case AdminWorkspaceTransferOwnership = '/api/admin/workspaces/{workspaceId:number}/transfer-ownership';

	case Mcp = '/mcp';

	case OAuthMetadata = '/.well-known/oauth-authorization-server/mcp';
	case OAuthResourceMetadata = '/.well-known/oauth-protected-resource/mcp';
	case OAuthAuthorize = '/mcp/oauth/authorize';
	case OAuthToken = '/mcp/oauth/token';
	case OAuthRegister = '/mcp/oauth/register';
	case OAuthClientInfo = '/mcp/oauth/client-info';
}
