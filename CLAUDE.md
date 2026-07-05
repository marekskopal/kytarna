# Ukolio

Minimalistic, multi-tenant project & task manager — work shown as a board,
table, calendar, or timeline. AI agents (over MCP) and humans (over the web UI)
are equal first-class actors: both can plan, create, move, and close work.

## Services

- `proxy/` — nginx reverse proxy (`/api/*` → backend, `/` → frontend)
- `backend/` — FrankenPHP + PHP 8.5 + `marekskopal/orm` +
  `marekskopal/router` + MariaDB
- `frontend/` — Angular 22 (standalone components + signals), SCSS design
  tokens (`frontend/src/styles/_variables.scss` + `_mixins.scss`). No Tailwind.

## Repository & branches (this clone has two remotes)

> This section lives on `main` only — do not copy it to the `public` branch.

- `origin` → `git@github.com:marekskopal/ukolio-web.git` — **private**. Holds the
  full SaaS: the open-source core **plus** the private bits — `web/` (marketing
  site), the SaaS proxy, and anything not meant to be public.
- `public` (remote) → `git@github.com:marekskopal/ukolio.git` — the
  **open-source** repo (`backend/`, `frontend/`, `proxy/`, docs).
- `main` (branch, tracks `origin/main`) — the private SaaS branch. Deploys the
  app under `/app` with the marketing site at `/`; its proxy is a single
  `proxy/conf/default.conf.template`.
- `public` (local branch, tracks `public/main`) — the open-source branch. Serves
  the app at `/`; its proxy uses `proxy/conf/{http,ssl}.conf.template` selected
  by `proxy/docker-entrypoint.d/10-select-template.sh`.

**Flow:** commit public-shareable work on the `public` branch (push to the
`public` remote), then `git merge public` into `main`. `main` = `public` + the
private additions. The `proxy/` config diverges by branch: `main` **renamed**
`ssl.conf.template` → `default.conf.template` and dropped `http.conf.template` +
the entrypoint selector, so merges from `public` raise a rename/modify conflict
on that file — resolve it on the `main` side (keep the `/app` SaaS layout).
Frontend code is base-href-agnostic (e.g. asset URLs resolve via
`document.baseURI`), so the same build works under `/` and `/app/`.

## Domain

- `Workspace` (owner, name) — top-level tenant; users belong to one or more workspaces.
- `WorkspaceUser` (workspace, user, role ∈ Owner/Admin/Member) — membership.
- `Invitation` (workspace, inviter, email, tokenHash, role, expiresAt, acceptedAt?) — pending invites.
- `User` (email, password, name, currentWorkspaceId?, systemRole ∈ User/SystemAdmin, locale) — `currentWorkspaceId` scopes data; `systemRole = SystemAdmin` grants global admin.
- `Project` (workspace, name, description) → has one `Workflow`, many `Tasks`, many `ProjectField` attachments.
- `Workflow` (project, name) → has many `Status`.
- `Status` (workflow, name, color, position, type ∈ Start/Normal/Finish).
- `Task` (project, status, name, description [markdown], priority, dueDate, startDate?, position, createdByAgent, archivedAt?) → has many `TaskFieldValue`. `createdByAgent = true` when the row was created via the MCP transport. `startDate` (nullable date) pairs with `dueDate` to span the Timeline view; the create/update endpoints + MCP `create_task`/`update_task` reject `startDate > dueDate`. `archivedAt` (nullable timestamp) is set when the task is archived; archived tasks are hidden from boards and from the default task list/MCP `list_tasks` but remain editable and can be unarchived.
- `Field` (workspace, name, type ∈ Text/Textarea/Select/Version, required, defaultValue, options) — per-workspace custom-field catalog.
- `ProjectField` (project, field, position, required) — attaches a workspace field to a project and orders it in the task drawer.
- `TaskFieldValue` (task, field, value) — concrete value per task.
- `TaskChecklistItem` (task, text, position, checkedAt?, checkedBy?, dueDate?, assignee?) — lightweight, ordered checklist items inside a task (U-69), distinct from subtasks (full `Task` rows + relations): checklists = intra-task steps, subtasks = real linked tasks. Done is derived from `checkedAt != null`; `checkedBy` records who ticked it; `dueDate`/`assignee` are optional per item (cf. Trello Advanced Checklists). Reordering renumbers `position`. Deleting a task deletes its items (`TaskProvider::deleteTask` → `TaskChecklistProvider::deleteAllForTask`). N-of-M counts ride on board/list task DTOs as `checklistTotal`/`checklistDone`.
- `TaskTemplate` (workspace, name, payload JSON) — reusable task snapshot (task name, description, priorityId, fieldValues, tagIds). Saved from an existing task; any member may manage them (`canManageTaskTemplates`). Stale tag/priority ids in the payload are dropped / fall back to the default priority on instantiation.
- `TaskComment` (task, author, body [markdown], actorType ∈ Human/Agent, mcpClient*, parentCommentId?, editedAt?) — discussion on a task. `parentCommentId` is a plain nullable FK id (not an ORM relation — the ORM can't eager-load a self-referential `ManyToOne` because the self-join reuses the table alias); threads are **clamped to one level**, so a reply to a reply re-parents to the top-level comment (`TaskCommentProvider::createComment`). `editedAt` (null = never edited) drives the "edited" flag — derived from a dedicated column rather than comparing `created_at`/`updated_at`, which only have second precision. Any member may add/reply/delete (own, or any if Admin/Owner — `canDeleteTaskComment`); editing is **author-only** (`canEditTaskComment`). Deleting a top-level comment cascades to its replies (FK is RESTRICT, so `deleteComment` removes children first). `@[Display Name](user:ID)` mention tokens in the body are parsed against workspace members and recorded as `mentionedUserIds` on the `TaskCommentAdded`/`TaskCommentEdited` event metadata — consumed by the U-83 notification fan-out.
- `Notification` (user [recipient FK], workspaceId, type ∈ TaskAssigned/TaskComment/TaskMention/TaskMoved/DueSoon/DueToday, taskId?, projectId?, actorId?, actorName?, data [JSON], readAt?) — per-user in-app inbox (U-83). `taskId`/`projectId`/`actorId` are plain ints (not FKs, mirroring `Event.taskId`) so a notification survives the task it points at; `data` holds taskCode/taskName/statusName/commentSnippet/dueDate rendered to text by the frontend via i18n (locale-agnostic). Created by `NotificationDispatcher`, which hooks `EventProvider::recordEvent` like the script event-trigger: it resolves recipients (watchers ∪ assignee ∪ mentioned), never notifies the actor, and **suppresses `TaskMoved` notifications when the actor is an Agent** (agents churn statuses). Emailable types (Mention/Assigned/DueSoon/DueToday) also enqueue an email via the `notification` queue; comment/move pings are in-app only. Assignment rides a dedicated `TaskAssigned` event recorded by `TaskProvider` when the assignee changes.
- `TaskWatcher` (task, user, unique per pair) — Trello-style task subscription (U-83). Auto-added when a user is assigned, comments, or is mentioned; togglable manually. Watchers receive comment/move/due notifications. Deleting a task removes its watchers (`TaskProvider::deleteTask` → `TaskWatcherProvider::deleteAllForTask`; FKs also cascade).
- `TaskRecurrence` (task [current carrier], createdBy, cadence ∈ Daily/Weekly/Monthly/Cron, interval, anchorDate, weekday?, dayOfMonth?, cronExpression?, endType ∈ Never/OnDate/AfterCount, endDate?, maxOccurrences?, occurrenceCount, nextRunAt?, lastSpawnedAt?, active) — recurring-tasks rule (U-67). **Single-carrier invariant**: exactly one open task carries a series; spawning an occurrence creates a fresh task (`duplicateTask` carry-over — name/description/priority/assignee/fields/tags — plus the checklist copied reset-to-unchecked, due/start shifted to the period) and re-points the recurrence to it. **Hybrid spawn**: `RecurrenceTrigger` (hooked into `EventProvider::recordEvent` like the notification/script dispatchers) enqueues the next occurrence to the `recurring-task-spawn` queue when a carrier is moved into a `Finish` status; the `recurring-tasks:tick` hourly cron (in-container supercronic, see DEPLOY.md) enqueues date-anchored series whose `nextRunAt` passed even if never completed. `RecurringTaskSpawnHandler` does the spawn and dedups via a carrier-id re-check (`RecurrenceScheduler` computes the absolute schedule; `nextRunAt`/`lastSpawnedAt` guard double-spawn). Set/cleared via `TaskRecurrenceProvider` (`canManageTasks`); deleting a task deletes its recurrence (`TaskProvider::deleteTask` removes the row directly to avoid a provider cycle). Records `TaskRecurrenceSet`/`TaskRecurrenceCleared`/`TaskRecurrenceSpawned` events.
- `Event` (author, type, metadata JSON, project?, workspaceId?, taskId?, actorType ∈ Human/Agent, mcpClientId?, mcpClientName?) — append-only audit log; `project`/`workspaceId` nullable so workspace- and admin-level events fit alongside project events. `actorType` + `mcpClient*` are set by `ActorContext`, which `McpController` flips to `Agent` after OAuth-token validation.

On sign-up a personal `Workspace` is auto-created and the user becomes its
owner. New `Project` auto-seeds workflow `To Do → In Progress → Done`.
Inviting a member sends an email via Symfony Mailer (SMTP env:
`SMTP_HOST/PORT/USER/PASSWORD`, `EMAIL_FROM`); `mailpit` is wired in
`docker-compose.yml` for local capture.

## Roles & permissions

Authorization is centralized in `Ukolio\Service\Auth\PermissionChecker`
(interface + impl). Every mutating controller and the SystemAdmin endpoints
route their decisions through it.

- **SystemAdmin** (`User.systemRole`): global; passes every `can*` check. Operates on workspaces they don't belong to via dedicated `/api/admin/*` endpoints (see `Ukolio\Controller\Admin\`) with a separate frontend at `/admin/users` and `/admin/workspaces`. Inside their own workspaces they act as a normal member of whatever role they hold.
- **Owner** (workspace-scoped): one per workspace. Rename/delete workspace, manage all members, transfer ownership (sole way to assign a new Owner).
- **Admin** (workspace-scoped): manage members (Member ↔ Admin), invite Members (cannot invite Admins or Owners), full CRUD on projects, workflows, statuses, custom fields, and tasks. Cannot remove or demote the Owner.
- **Member** (workspace-scoped): full CRUD on tasks; read-only on projects, workflows, statuses, and fields.

Ownership transfer (`POST /api/workspaces/{id}/transfer-ownership`)
atomically updates `Workspace.owner` and both `WorkspaceUser` rows (old Owner
becomes Admin). Workspace owner removal is blocked — transfer first.

The first SystemAdmin is provisioned out-of-band via
`docker compose exec backend php bin/console admin:create` (see DEPLOY.md).
Earlier builds seeded a default `admin@ukolio.com` / `admin`; migration
`20260520_120000_InvalidateDefaultAdminPassword` neutralises that account on
existing installs by replacing its password with an unverifiable string when
the default is still in place.

MCP tools remain scoped to `currentWorkspace` — sysadmins must use the web
admin UI for cross-workspace management.

## HTTP API surface

All routes live in `Ukolio\Route\Routes` (single enum). Highlights:

- `POST /api/authentication/{login,logout,sign-up,refresh-token}` — `logout` is open and expires the HttpOnly Mercure subscriber cookie (web JWTs are stateless; it does not revoke them).
- `GET/PATCH /api/current-user`
- `GET/POST /api/workspaces`, `PUT/DELETE /api/workspaces/{id}`, plus `/switch`, `/members`, `/transfer-ownership`, `/invitations`, `/fields`, `/mcp-clients`, `/events`, `/agent-stats`.
- `GET/POST/PUT/DELETE /api/invitations/...`
- `GET/POST/PUT/DELETE /api/projects[/{id}]`, plus `/board`, `/events`, `/workflow`, `/tasks`, `/fields`.
- `GET /api/workflows` — workspace-wide list of workflows with nested statuses + `projectName` (used by the Tasks grid's status filter).
- `GET/POST/PUT/DELETE /api/workflows/{id}/statuses`, `/api/statuses/{id}`, `/api/statuses/{id}/move`.
- `GET /api/tasks` — workspace-wide paginated list. Query params: `limit` (default 50, max 200), `offset`, `orderBy` (`created_at|name|status_id`), `orderDirection` (`ASC|DESC`), `search`, `statusIds` (pipe-delimited), `onlyActive` (status type ≠ Finish), `subtaskFilter` (`all|hideSubtasks|onlyParents`), `archived` (`active` (default)|`archived`|`all`), `dueFrom`/`dueTo` (inclusive `YYYY-MM-DD` due-date range, used by the Calendar view to scope a month/week window; param parsing lives in `TaskListQueryDto`). Response shape: `{ tasks: TaskListItemDto[], count: int }`. List items carry `subtasksTotal`/`subtasksDone` and `checklistTotal`/`checklistDone` (also on board tasks) for the N-of-M progress chips.
- `GET/PUT/DELETE /api/tasks/{id}`, `PUT /api/tasks/{id}/move`, `POST /api/tasks/{id}/archive`, `POST /api/tasks/{id}/unarchive`, `POST /api/projects/{id}/tasks`. Archiving records a `TaskArchived` event (unarchiving a `TaskUnarchived` event).
- `POST /api/tasks/{id}/duplicate` — clones name (+" (copy)"), description, priority, due date, assignee, field values, tags; not comments/files/events/relations.
- Subtasks (= `TaskRelation` of type `Parent`, source is the parent): `GET /api/tasks/{id}/subtasks` (children incl. `relationId`, `statusType`, and the child project's `startStatusId`/`finishStatusId` so the UI toggles done/undone via the plain move endpoint), `POST /api/tasks/{id}/subtasks` (`{name}` quick-add: creates the child in the parent's project Start status and links it). Cascade rule: deleting a parent **orphans** its children — relations are removed, child tasks survive as top-level (`TaskProvider::deleteTask` → `deleteAllForTask`).
- Checklist: `GET/POST /api/tasks/{id}/checklist` (list ordered items / append `{text, dueDate?, assigneeId?}`), `PUT/DELETE /api/checklist-items/{itemId}` (partial update — `text`/`dueDate`/`assigneeId`/`checked`, sent keys win; omitting leaves unchanged, explicit `null` clears), `PUT /api/checklist-items/{itemId}/move` (`{position}` reorder). All gated by `canManageTasks`.
- Comments: `GET/POST /api/tasks/{id}/comments` (list chronologically / append `{body, parentCommentId?}` — `parentCommentId` makes it a threaded reply, clamped to one level), `PUT /api/task-comments/{commentId}` (`{body}` edit, author-only), `DELETE /api/task-comments/{commentId}` (author or Admin/Owner; cascades to replies). List items carry `parentCommentId` + `edited`. Adding/editing records `TaskCommentAdded`/`TaskCommentEdited` with `mentionedUserIds` parsed from `@[Name](user:ID)` tokens.
- Notifications (U-83, per authenticated user): `GET /api/notifications` (`?unreadOnly&limit&offset`) → `{ notifications, unreadCount }`, `GET /api/notifications/unread-count`, `POST /api/notifications/{id}/read`, `POST /api/notifications/read-all`, `DELETE /api/notifications/{id}`. Topbar bell subscribes to the Mercure stream; a `NotificationCreated` realtime ping carries the recipient `userId` (clients ignore pings not addressed to them).
- Watchers (U-83): `GET /api/tasks/{id}/watchers` → `{ watchers, watching }`, `POST /api/tasks/{id}/watch`, `DELETE /api/tasks/{id}/watch`. Gated by workspace membership (watching is a personal action). Due-date reminders are sent by the `notifications:due-tick` console command (hourly in-container cron, see DEPLOY.md) to assignee + watchers for tasks due today/tomorrow, de-duplicated per day.
- Recurrence (U-67): `GET /api/tasks/{id}/recurrence` → rule or `null`, `PUT /api/tasks/{id}/recurrence` (set/replace; body = cadence/interval/weekday/dayOfMonth/cronExpression/endType/endDate/maxOccurrences/anchorDate), `DELETE /api/tasks/{id}/recurrence` (clear). Gated by `canManageTasks`. The new-occurrence spawn runs in the background (`recurring-task-spawn` queue), not synchronously on the move.
- Templates: `GET /api/workspaces/{id}/task-templates`, `POST /api/tasks/{id}/save-as-template` (`{name}`), `DELETE /api/task-templates/{id}`. The UI "Create from template" prefills the new-task drawer client-side and goes through the normal create endpoint.
- Admin: `GET/PUT/DELETE /api/admin/users[/{id}]`, `GET/PUT/DELETE /api/admin/workspaces[/{id}]`, plus `/members`, `/transfer-ownership`.
- MCP: `POST/GET/DELETE /mcp`, OAuth discovery + flow endpoints (see below).

Query enums live under `backend/src/Model/Repository/Enum/`
(`OrderDirectionEnum`, `TaskOrderByEnum`).

## Frontend routes

Public:

- `/login`, `/sign-up`, `/invitations/accept`

Inside `LayoutComponent` (AuthGuard-protected):

- `/projects`, `/projects/new`, `/projects/:id/edit`, `/projects/:id/board`, `/projects/:id/workflow`, `/projects/:id/events`
- `/tasks` — workspace-wide grid (see below)
- `/workspaces` — membership management
- `/admin/users`, `/admin/workspaces` — SystemAdmin only

Shared components live under `frontend/src/app/shared/components/`
(`layout`, `alert`, `pagination`).

### Tasks grid (`/tasks`)

Workspace-scoped paginated table. State is held as signals in
`TasksGridComponent` — no URL or localStorage
persistence (yet). Filter / sort / page-size changes reset to page 1. Row
click opens the existing `TaskDetailDrawerComponent` in place — the drawer is
already cleanly parameterized (`task`, `statuses`, `projectId`,
`projectFields` inputs; `saved`/`deleted`/`cancelled` outputs) and is reused
without refactor. Reusable `PaginationComponent` lives in
`frontend/src/app/shared/components/pagination/` with options
`[25, 50, 100, 200]` (default 50).

## i18n

- Backend: `Ukolio\Service\Translator\TranslatorService` loads `backend/translations/{en,cs}.json`. `EmailFactory` renders subject + section per `User.locale`; invitee's locale falls back to the inviter when they don't yet have an account.
- Frontend: `@ngx-translate/core` + `@ngx-translate/http-loader`. JSONs live in `frontend/src/i18n/{en,cs}.json`, served from `/i18n/` via `angular.json` assets. `LanguageService` initialises from `?lang=`, then localStorage, then `navigator.language`. `PATCH /api/current-user` syncs the user's choice to the backend so emails arrive in the right language. The topbar has a language switcher.

## Docker

```bash
docker compose up -d --build              # Full stack
docker compose --profile dev up -d        # +Adminer
make migrate                              # Apply migrations
```

## MCP server

Exposed at `POST/GET/DELETE /mcp` (Streamable HTTP transport, `mcp/sdk`).
Sessions are persisted to Redis with a TTL of `MCP_SESSION_TTL` seconds
(default 86400).

Auth is **OAuth 2.1 with PKCE**. Discovery endpoints:

- `GET /.well-known/oauth-authorization-server/mcp` — issuer/authz/token/registration URLs
- `GET /.well-known/oauth-protected-resource/mcp` — resource metadata
- `POST /mcp/oauth/register` — dynamic client registration (open; `redirect_uri` must be `https`, or `http` for loopback)
- `POST /mcp/oauth/authorize` — user approval (requires user JWT)
- `POST /mcp/oauth/token` — code/refresh-token exchange (open)
- `GET /mcp/oauth/client-info` — display name lookup (open)

401 responses include `WWW-Authenticate: Bearer resource_metadata="…"` per
RFC 9728 so MCP clients can auto-discover. PKCE `S256` only; no client
secret. Access token lifetime 1 h, refresh 30 d. Storage: `oauth_clients` and
`oauth_authorizations` tables (tokens stored as SHA-256 hashes).

Tools live in `backend/src/Mcp/Tool/` (auto-discovered by basePath/scanDirs):

- `ProjectTools` — list/find/get/create/delete projects
- `WorkflowTools` — list/find statuses for a project's workflow
- `TaskTools` — list/find/get/create/update/move/archive/unarchive/duplicate/delete tasks (move accepts `statusId` or `statusName`; `list_tasks` hides archived unless `includeArchived: true`)
- `TaskRelationTools` — `list_task_relations`, `link_tasks`, `unlink_tasks`, `create_subtask` (create + Parent-link in one call)
- `TaskChecklistTools` — `list_task_checklist` (items + progress), `add_checklist_item`, `update_checklist_item` (`dueDate=""` clears, `clearAssignee` unassigns, `checked` toggles), `toggle_checklist_item`, `delete_checklist_item`
- `TaskRecurrenceTools` — `get_task_recurrence`, `set_task_recurrence` (cadence/interval/weekday/dayOfMonth/cronExpression/endType/endDate/maxOccurrences/anchorDate), `clear_task_recurrence`; `create_task` also accepts an optional `recurrence` payload of the same shape
- `TaskCommentTools` — `list_task_comments`, `add_task_comment` (agent-tagged; optional `parentCommentId` for a threaded reply; `@[Name](user:ID)` tokens mention members), `update_task_comment` (author-only edit)
- `TaskTemplateTools` — `list_task_templates`, `save_task_as_template`, `create_task_from_template` (defaults to Start status; accepts name/status overrides)
- `FieldTools` — manage the workspace's custom-field catalog and per-project attachments
- `TaskFileTools` — `list_task_files`, `get_task_file`, `attach_file`, `delete_task_file` (file attachments on a task)
- `TagTools` — `list_workspace_tags`, `find_tag_by_name`, `create_tag`, `update_tag`, `delete_tag`, `set_task_tags`
- `PriorityTools` — `list_workspace_priorities`, `find_priority_by_name`, `create_priority`, `update_priority`, `delete_priority`
- `MemberTools` — `list_workspace_members`, `find_member_by_email`, `invite_member`
- `SearchTools` — `search_tasks` (full-text search across the workspace's tasks)
- `EventTools` — `list_events` (workspace audit log, filter by `projectId`/`taskId`/`type`), `list_task_events` (by task id or code). Event `createdAt` is ISO 8601; `TaskMoved` metadata carries `toStatusId`/`toStatusName`, so a script/agent can tell when a task entered a status.
- `ScriptTools` — `list_scripts`, `get_script`, `create_script`, `update_script`, `delete_script`, `run_script` (async one-off), `list_script_runs`. Mutations require workspace admin (`canManageScripts`). Scheduled triggers take a 5-field cron in `triggerConfig`.

The MCP surface mirrors the web UI: agents and humans are equal first-class
actors, each able to plan, create, move, and close work.

## Testing

```bash
make test                    # All tests (backend + frontend + e2e)
make test-backend            # PHPUnit (runs inside the backend container)
make test-backend-coverage   # +pcov HTML report at backend/.phpunit.cache/coverage-html
make test-frontend           # Vitest (jsdom + @analogjs/vite-plugin-angular)
make test-e2e                # Playwright (boots the docker stack via webServer)
make test-e2e-ui             # Playwright UI mode
```

Backend tests boot the full `ApplicationFactory` container against a separate
MariaDB database (`ukolio_test`, auto-created by `tests/bootstrap.php`) and
truncate tables between tests via `IntegrationTestCase`. Test helpers live in
`backend/tests/Support/` — `AppHarness` (per-suite singleton),
`IntegrationTestCase` (HTTP dispatch + DB reset), and `Fixture` (deterministic
user/workspace/project/JWT builders). `phpunit.xml` scopes coverage to
`src/{Controller,Mcp,Service,OAuth,Validator}`.

Frontend tests use Vitest 4 with jsdom and the AnalogJS Vite plugin (config
in `frontend/vitest.config.ts`, TestBed bootstrap in `frontend/src/test-setup.ts`).
The app is zoneless, so specs **must not** import `zone.js/testing` — use
`provideZonelessChangeDetection()` in TestBed providers and the standard
`fixture.detectChanges()` / `await fixture.whenStable()` lifecycle.

- File naming: `*.spec.ts` co-located next to the unit under test.
- Shared TestBed boilerplate lives in `frontend/src/app/testing/test-providers.ts` —
  prefer `commonTestProviders()` (zoneless + router + HTTP testing) and
  `provideTranslateStub()` (covers any component whose template uses `TranslatePipe`).
  The stub mirrors the ngx-translate v18 API, where `TranslatePipe` consumes a
  signal-returning `translate(key, params)` method (not just `instant`/`get`).
- ngx-markdown v22 parses asynchronously and writes `innerHTML` from a floating
  promise that `whenStable()` doesn't track. Specs that read rendered markdown
  must flush microtasks (`await new Promise(r => setTimeout(r))`) and re-run
  `detectChanges()` before asserting — see `markdown-editor.component.spec.ts`.
- Run: `pnpm run test` (single run) or `pnpm run test:watch`. `make test-frontend`
  is the equivalent from the repo root.

End-to-end tests use Playwright (config in `frontend/playwright.config.ts`).
Specs live in `frontend/e2e/` with page objects under `frontend/e2e/pages/`.

- The `setup` Playwright project (`e2e/setup/auth.setup.ts`) signs up a fresh
  fixture user per run and writes `e2e/.auth/user.json` (storage state) plus
  `e2e/.auth/credentials.json` (for specs that need to log in again).
- The default `chromium` project reuses that storage state. Auth specs
  (`sign-up.spec.ts`, `login.spec.ts`) opt out via `test.use({storageState: {cookies: [], origins: []}})`.
- `webServer` invokes `docker compose up -d --build --wait` from the repo root.
  `reuseExistingServer: true` makes the run a no-op when `make up` is already
  running. Override the URL with `E2E_BASE_URL=...` and disable the auto-up
  with `E2E_SKIP_WEBSERVER=1` (CI with an external stack).
- Self-signed certs in dev are ignored (`ignoreHTTPSErrors: true`).
- Credentials default to `Test1234!`; override with `E2E_USER_EMAIL` / `E2E_PASSWORD`
  in `.env.test` at the repo root.
- Coverage: sign-up + login + workspace switch/create + project CRUD +
  workflow status CRUD + task CRUD (create → edit → move across statuses →
  delete).

## Linting

Backend uses PHPStan at `max` level (with `bleedingEdge.neon` +
strict/deprecation/phpunit/shipmonk rules + cognitive-complexity +
unused-public) and PHPCS with the slevomat ruleset (tabs, single-line method
signatures ≤140 chars). Custom PHPStan extension
`Ukolio\PhpStan\OrmReadWritePropertiesExtension` marks
`Column`/`ManyToOne`/`ColumnEnum`-attributed properties as ORM-managed
(always read, always written, always initialized).

Frontend uses angular-eslint + `@typescript-eslint`, with
`simple-import-sort` and `unused-imports`. `pnpm run lint` runs with
`--max-warnings=0`.

```bash
make lint           # PHPStan + PHPCS
make lint-fix       # phpcbf auto-fix
```
