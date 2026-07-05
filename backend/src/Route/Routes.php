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
	case WorkspaceTags = '/api/workspaces/{workspaceId:number}/tags';
	case WorkspaceTag = '/api/workspaces/{workspaceId:number}/tags/{tagId:number}';
	case WorkspaceMcpClients = '/api/workspaces/{workspaceId:number}/mcp-clients';
	case WorkspaceMcpClientRevoke = '/api/workspaces/{workspaceId:number}/mcp-clients/{clientId:[a-f0-9]+}/revoke';
	case WorkspaceEvents = '/api/workspaces/{workspaceId:number}/events';
	case WorkspaceAgentStats = '/api/workspaces/{workspaceId:number}/agent-stats';
	case Invitation = '/api/invitations/{invitationId:number}';
	case InvitationLookup = '/api/invitations/lookup';
	case InvitationAccept = '/api/invitations/accept';

	case Courses = '/api/courses';
	case Course = '/api/courses/{courseId:number}';
	case CourseBoard = '/api/courses/{courseId:number}/board';
	case CourseEvents = '/api/courses/{courseId:number}/events';
	case CourseWorkflow = '/api/courses/{courseId:number}/workflow';
	case CourseLectures = '/api/courses/{courseId:number}/lectures';
	case CoursePracticeSummary = '/api/courses/{courseId:number}/practice-summary';

	case Workflows = '/api/workflows';
	case WorkflowStatuses = '/api/workflows/{workflowId:number}/statuses';
	case Status = '/api/statuses/{statusId:number}';
	case StatusMove = '/api/statuses/{statusId:number}/move';

	case Lectures = '/api/lectures';
	case LecturesBulk = '/api/lectures/bulk';
	// lectureId pattern accepts numeric IDs and course-prefixed codes (uppercase + dash, e.g. MP-3).
	// Lowercase is intentionally excluded so static sibling paths like /api/lectures/bulk don't collide.
	case Lecture = '/api/lectures/{lectureId:[A-Z0-9][A-Z0-9-]*}';
	case LectureMove = '/api/lectures/{lectureId:[A-Z0-9][A-Z0-9-]*}/move';
	case LectureArchive = '/api/lectures/{lectureId:[A-Z0-9][A-Z0-9-]*}/archive';
	case LectureUnarchive = '/api/lectures/{lectureId:[A-Z0-9][A-Z0-9-]*}/unarchive';
	case LectureFiles = '/api/lectures/{lectureId:[A-Z0-9][A-Z0-9-]*}/files';
	case LectureFile = '/api/lectures/{lectureId:[A-Z0-9][A-Z0-9-]*}/files/{fileId:number}';
	case LectureFileContent = '/api/lectures/{lectureId:[A-Z0-9][A-Z0-9-]*}/files/{fileId:number}/content';
	case LectureWatchers = '/api/lectures/{lectureId:[A-Z0-9][A-Z0-9-]*}/watchers';
	case LectureWatch = '/api/lectures/{lectureId:[A-Z0-9][A-Z0-9-]*}/watch';

	case LectureTabs = '/api/lectures/{lectureId:[A-Z0-9][A-Z0-9-]*}/tabs';
	case LectureTabsImport = '/api/lectures/{lectureId:[A-Z0-9][A-Z0-9-]*}/tabs/import';
	case Tab = '/api/tabs/{tabId:number}';

	case LectureProgress = '/api/lectures/{lectureId:[A-Z0-9][A-Z0-9-]*}/progress';
	case LecturePracticeSummary = '/api/lectures/{lectureId:[A-Z0-9][A-Z0-9-]*}/practice-summary';
	case ProgressEntry = '/api/progress/{progressEntryId:number}';

	case LectureLinks = '/api/lectures/{lectureId:[A-Z0-9][A-Z0-9-]*}/links';
	case LectureLink = '/api/lectures/{lectureId:[A-Z0-9][A-Z0-9-]*}/links/{linkId:number}';

	case Notifications = '/api/notifications';
	case NotificationsReadAll = '/api/notifications/read-all';
	case NotificationsUnreadCount = '/api/notifications/unread-count';
	case NotificationRead = '/api/notifications/{notificationId:number}/read';
	case Notification = '/api/notifications/{notificationId:number}';


	case WorkspaceSavedViews = '/api/workspaces/{workspaceId:number}/saved-views';
	case SavedView = '/api/saved-views/{savedViewId:number}';



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
