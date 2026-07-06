# Kytarna

Minimalistic, multi-tenant project & task manager — work shown as a board,
table, calendar, or timeline. AI agents (over MCP) and humans (over the web UI)
are equal first-class actors: both can plan, create, move, and close work.

## Services

- `proxy/` — nginx reverse proxy (`/api/*` → backend, `/` → frontend)
- `backend/` — FrankenPHP + PHP 8.5 + `marekskopal/orm` +
  `marekskopal/router` + MariaDB
- `frontend/` — Angular 22 (standalone components + signals), SCSS design
  tokens (`frontend/src/styles/_variables.scss` + `_mixins.scss`). No Tailwind.

## Domain

- `Workspace` (owner, name) — top-level tenant; users belong to one or more workspaces.
- `WorkspaceUser` (workspace, user, role ∈ Owner/Admin/Member) — membership.
- `Invitation` (workspace, inviter, email, tokenHash, role, expiresAt, acceptedAt?) — pending invites.
- `User` (email, password, name, currentWorkspaceId?, systemRole ∈ User/SystemAdmin, locale) — `currentWorkspaceId` scopes data; `systemRole = SystemAdmin` grants global admin.
- `Project` (workspace, name, description) → has one `Workflow` and many `Tasks`.
- `Workflow` (project, name) → has many `Status`.
- `Status` (workflow, name, color, position, type ∈ Start/Normal/Finish).
- `Task` (project, status, name, description [markdown], dueDate, startDate?, position, createdByAgent, archivedAt?). `createdByAgent = true` when the row was created via the MCP transport. `startDate` (nullable date) pairs with `dueDate` to span the Timeline view; the create/update endpoints + MCP `create_task`/`update_task` reject `startDate > dueDate`. `archivedAt` (nullable timestamp) is set when the task is archived; archived tasks are hidden from boards and from the default task list/MCP `list_tasks` but remain editable and can be unarchived.
- `Notification` (user [recipient FK], workspaceId, type ∈ TaskAssigned/TaskMoved/DueSoon/DueToday, taskId?, projectId?, actorId?, actorName?, data [JSON], readAt?) — per-user in-app inbox (U-83). `taskId`/`projectId`/`actorId` are plain ints (not FKs, mirroring `Event.taskId`) so a notification survives the task it points at; `data` holds taskCode/taskName/statusName/dueDate rendered to text by the frontend via i18n (locale-agnostic). Created by `NotificationDispatcher`, which hooks `EventProvider::recordEvent`: it resolves recipients (watchers ∪ assignee), never notifies the actor, and **suppresses `TaskMoved` notifications when the actor is an Agent** (agents churn statuses). Emailable types (Assigned/DueSoon/DueToday) also enqueue an email via the `notification` queue; move pings are in-app only. Assignment rides a dedicated `TaskAssigned` event recorded by `TaskProvider` when the assignee changes.
- `TaskWatcher` (task, user, unique per pair) — Trello-style task subscription (U-83). Auto-added when a user is assigned; togglable manually. Watchers receive move/due notifications. Deleting a task removes its watchers (`TaskProvider::deleteTask` → `TaskWatcherProvider::deleteAllForTask`; FKs also cascade).
- `Event` (author, type, metadata JSON, project?, workspaceId?, taskId?, actorType ∈ Human/Agent, mcpClientId?, mcpClientName?) — append-only audit log; `project`/`workspaceId` nullable so workspace- and admin-level events fit alongside project events. `actorType` + `mcpClient*` are set by `ActorContext`, which `McpController` flips to `Agent` after OAuth-token validation.

On sign-up a personal `Workspace` is auto-created and the user becomes its
owner. New `Project` auto-seeds workflow `To Do → In Progress → Done`.
Inviting a member sends an email via Symfony Mailer (SMTP env:
`SMTP_HOST/PORT/USER/PASSWORD`, `EMAIL_FROM`); `mailpit` is wired in
`docker-compose.yml` for local capture.

## Roles & permissions

Authorization is centralized in `Kytarna\Service\Auth\PermissionChecker`
(interface + impl). Every mutating controller and the SystemAdmin endpoints
route their decisions through it.

- **SystemAdmin** (`User.systemRole`): global; passes every `can*` check. Operates on workspaces they don't belong to via dedicated `/api/admin/*` endpoints (see `Kytarna\Controller\Admin\`) with a separate frontend at `/admin/users` and `/admin/workspaces`. Inside their own workspaces they act as a normal member of whatever role they hold.
- **Owner** (workspace-scoped): one per workspace. Rename/delete workspace, manage all members, transfer ownership (sole way to assign a new Owner).
- **Admin** (workspace-scoped): manage members (Member ↔ Admin), invite Members (cannot invite Admins or Owners), full CRUD on projects, workflows, statuses, tags, and tasks. Cannot remove or demote the Owner.
- **Member** (workspace-scoped): full CRUD on tasks; read-only on projects, workflows, statuses, and tags.

Ownership transfer (`POST /api/workspaces/{id}/transfer-ownership`)
atomically updates `Workspace.owner` and both `WorkspaceUser` rows (old Owner
becomes Admin). Workspace owner removal is blocked — transfer first.

The first SystemAdmin is provisioned out-of-band via
`docker compose exec backend php bin/console admin:create` (see DEPLOY.md).

MCP tools remain scoped to `currentWorkspace` — sysadmins must use the web
admin UI for cross-workspace management.

## HTTP API surface

All routes live in `Kytarna\Route\Routes` (single enum). Highlights:

- `POST /api/authentication/{login,logout,sign-up,refresh-token}` — `logout` is an open route (web JWTs are stateless; it does not revoke them).
- `GET/PATCH /api/current-user`
- `GET/POST /api/workspaces`, `PUT/DELETE /api/workspaces/{id}`, plus `/switch`, `/members`, `/transfer-ownership`, `/invitations`, `/tags`, `/mcp-clients`, `/events`, `/agent-stats`.
- `GET/POST/PUT/DELETE /api/invitations/...`
- `GET/POST/PUT/DELETE /api/projects[/{id}]`, plus `/board`, `/events`, `/workflow`, `/tasks`.
- `GET /api/workflows` — workspace-wide list of workflows with nested statuses + `projectName` (used by the Tasks grid's status filter).
- `GET/POST/PUT/DELETE /api/workflows/{id}/statuses`, `/api/statuses/{id}`, `/api/statuses/{id}/move`.
- `GET /api/tasks` — workspace-wide paginated list. Query params: `limit` (default 50, max 200), `offset`, `orderBy` (`created_at|name|status_id`), `orderDirection` (`ASC|DESC`), `search`, `statusIds` (pipe-delimited), `tagIds`, `assigneeIds`, `onlyActive` (status type ≠ Finish), `archived` (`active` (default)|`archived`|`all`), `dueFrom`/`dueTo` (inclusive `YYYY-MM-DD` due-date range, used by the Calendar view to scope a month/week window; param parsing lives in `TaskListQueryDto`). Response shape: `{ tasks: TaskListItemDto[], count: int }`.
- `GET/PUT/DELETE /api/tasks/{id}`, `PUT /api/tasks/{id}/move`, `POST /api/tasks/{id}/archive`, `POST /api/tasks/{id}/unarchive`, `POST /api/projects/{id}/tasks`. Archiving records a `TaskArchived` event (unarchiving a `TaskUnarchived` event).
- Notifications (U-83, per authenticated user): `GET /api/notifications` (`?unreadOnly&limit&offset`) → `{ notifications, unreadCount }`, `GET /api/notifications/unread-count`, `POST /api/notifications/{id}/read`, `POST /api/notifications/read-all`, `DELETE /api/notifications/{id}`.
- Watchers (U-83): `GET /api/tasks/{id}/watchers` → `{ watchers, watching }`, `POST /api/tasks/{id}/watch`, `DELETE /api/tasks/{id}/watch`. Gated by workspace membership (watching is a personal action). Due-date reminders are sent by the `notifications:due-tick` console command (hourly in-container cron, see DEPLOY.md) to assignee + watchers for tasks due today/tomorrow, de-duplicated per day.
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
already cleanly parameterized (`task`, `statuses`, `projectId` inputs;
`saved`/`deleted`/`cancelled` outputs) and is reused without refactor. Reusable `PaginationComponent` lives in
`frontend/src/app/shared/components/pagination/` with options
`[25, 50, 100, 200]` (default 50).

## i18n

- Backend: `Kytarna\Service\Translator\TranslatorService` loads `backend/translations/{en,cs}.json`. `EmailFactory` renders subject + section per `User.locale`; invitee's locale falls back to the inviter when they don't yet have an account.
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
- `TaskTools` — list/find/get/create/update/move/archive/unarchive/delete tasks + `bulk_update_tasks` (move accepts `statusId` or `statusName`; `list_tasks` hides archived unless `includeArchived: true`)
- `TaskFileTools` — `list_task_files`, `get_task_file`, `attach_file`, `delete_task_file` (file attachments on a task)
- `TagTools` — `list_workspace_tags`, `find_tag_by_name`, `create_tag`, `update_tag`, `delete_tag`, `set_task_tags`
- `MemberTools` — `list_workspace_members`, `find_member_by_email`, `invite_member`
- `EventTools` — `list_events` (workspace audit log, filter by `projectId`/`taskId`/`type`), `list_task_events` (by task id or code). Event `createdAt` is ISO 8601; `TaskMoved` metadata carries `toStatusId`/`toStatusName`, so an agent can tell when a task entered a status.

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
MariaDB database (`kytarna_test`, auto-created by `tests/bootstrap.php`) and
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
`Kytarna\PhpStan\OrmReadWritePropertiesExtension` marks
`Column`/`ManyToOne`/`ColumnEnum`-attributed properties as ORM-managed
(always read, always written, always initialized).

Frontend uses angular-eslint + `@typescript-eslint`, with
`simple-import-sort` and `unused-imports`. `pnpm run lint` runs with
`--max-warnings=0`.

```bash
make lint           # PHPStan + PHPCS
make lint-fix       # phpcbf auto-fix
```
