<p align="center">
  <img src="frontend/src/assets/brand/logo-wordmark-inverse.svg" alt="Kytarna" width="320" />
</p>

<p align="center">
  <strong>The project &amp; task manager your <em>agents</em> can drive.</strong>
</p>

<p align="center">
  Multi-tenant work management built around the <a href="https://modelcontextprotocol.io">Model Context Protocol</a>.
  See your work as a board, table, calendar, or timeline — while Claude, Cursor, ChatGPT, or any MCP client
  plans, creates, moves, and closes tasks alongside your team.
  Self-hostable, MIT-licensed, EN + CS.
</p>

<p align="center">
  <a href="https://www.kytarna.com">www.kytarna.com</a> · MCP endpoint: <code>https://www.kytarna.com/mcp</code>
</p>

---

## Why Kytarna

- **MCP-native.** Streamable HTTP transport, session persistence, tools auto-discovered from the backend.
- **OAuth 2.1 + PKCE for agents.** No shared API keys, no copy-paste tokens — each agent has its own credential.
- **Human/Agent attribution.** Every event, comment, and task is tagged `Human` or `Agent`; append-only event log per workspace / project / task.
- **Multi-tenant.** Workspaces with Owner / Admin / Member roles, invitations, and a separate SystemAdmin tier for global operations.
- **Four views of your work.** The same tasks as a drag-and-drop Kanban board, a workspace-wide table with saved views, a calendar, or a timeline (Gantt-style, spanning start → due dates).
- **Rich tasks.** Markdown descriptions, subtasks and typed relations, checklists, threaded comments with `@mentions`, watchers and an in-app notification inbox, reusable task templates, archiving, custom fields, custom priorities, tags, assignees, file attachments, and realtime updates over Mercure.
- **Typo-tolerant search.** Meilisearch indexes task names, descriptions, comments, text custom-field values, and tags — exposed both on the web and as an MCP tool.
- **Scriptable automations.** Per-workspace JavaScript that runs in a hardened V8 sandbox (`ext-v8js`) — on a schedule, on workspace events, or on demand — with a typed `kytarna.*` host API, encrypted variables, and an in-app Monaco editor.

## Stack

| Layer    | Tech |
|----------|------|
| Proxy    | nginx |
| Frontend | Angular 22 (standalone components + signals), SCSS, ngx-translate |
| Backend  | FrankenPHP, PHP 8.5, [`marekskopal/orm`](https://github.com/marekskopal/orm), [`marekskopal/router`](https://github.com/marekskopal/router), Symfony Mailer |
| Scripting | Google V8 via [`ext-v8js`](https://github.com/phpv8/v8js) (ZTS build), isolated in a dedicated script-worker process |
| Database | MariaDB 11.4 |
| Cache    | Redis (sessions, rate limits, hot paths) + Memcached |
| Queue    | RabbitMQ — async jobs (search indexing, email, etc.) |
| Search   | Meilisearch — typo-tolerant full-text over tasks |
| Storage  | S3-compatible (MinIO in dev) for task file attachments |
| Realtime | Mercure hub for board / task push updates |
| Mail     | Mailpit (dev) / any SMTP (prod) |
| Auth     | JWT + optional Google sign-in for web, OAuth 2.1 + PKCE for MCP |

## Quick start

```bash
cp .env.example .env                                          # adjust ports / secrets as needed
openssl rand -hex 32                                          # run 3× — one value each for AUTHORIZATION_TOKEN_KEY,
                                                              # MERCURE_PUBLISHER_JWT_KEY, MERCURE_SUBSCRIBER_JWT_KEY
make up                                                       # build & start the full stack
make migrate                                                  # run database migrations
docker compose exec backend php bin/console admin:create      # bootstrap the first SystemAdmin
open http://localhost:4300/                                   # default proxy port
```

The backend refuses to boot when `AUTHORIZATION_TOKEN_KEY` is missing, shorter
than 32 characters, or still set to the `replace-with-32-char-random-hex-key-here`
placeholder. Generate a distinct value for it and for each of the two Mercure
JWT keys by running `openssl rand -hex 32` once per key (three times total).
With `APP_ENV=production` the same boot guard also
rejects the dev defaults for `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD`,
`S3_ACCESS_KEY`, and `S3_SECRET_KEY` — rotate them before going live.

`admin:create` prompts for email + password (or accepts
`--email`/`--password`/`--name` flags for non-interactive provisioning). See
[DEPLOY.md](DEPLOY.md) for deployment details and the upgrade note for
installs that previously shipped a default admin.

Anyone can also sign up at `/sign-up`; the first registration auto-creates a
personal workspace and drops the user into a 3-step onboarding wizard
(`/onboarding/step-1…3`). New accounts go through an email-verification flow
(`POST /api/authentication/verify-email`), and the standard password-reset
loop is wired (`request-password-reset` → `confirm-password-reset`). Setting
`GOOGLE_CLIENT_ID` enables one-click Google sign-in alongside email/password.

## Domain

- **Workspace** — top-level tenant; users belong to one or more workspaces.
- **WorkspaceUser** — membership with a role (`Owner` / `Admin` / `Member`).
- **Invitation** — pending email invite, signed token, expires after 7 days.
- **User** — `email`, `password` (nullable for Google-only accounts), `name`,
  `locale`, `theme` (`System` / `Light` / `Dark`), `currentWorkspaceId`,
  `systemRole` (`User` / `SystemAdmin`), `emailVerified`,
  `onboardingCompletedAt`, optional `googleId`, `defaultSavedViewId`.
  `currentWorkspaceId` scopes every web request.
- **Project** — workspace-scoped, with a short `prefix` (e.g. `MP`) used to
  mint task codes. Auto-seeds a `Workflow` of `To Do → In Progress → Done` on
  creation.
- **Workflow** → **Status** (`Start` / `Normal` / `Finish`, with name + color +
  position).
- **Priority** — workspace-scoped catalog (name + color + position +
  `isDefault`). Replaces the old hard-coded `Low` / `Medium` / `High` enum;
  every task references one.
- **Task** — project-scoped, lives in a Status, has name / Markdown description
  / `Priority` / position / optional assignee, an optional `startDate` + due
  date pair (spanning the timeline view), and a nullable `archivedAt`.
  `sequenceNumber` combined with the project's `prefix` gives a stable public
  code (e.g. `MP-3`) used in URLs and MCP `get_task`. `createdByAgent = true`
  when the row was created via MCP. Archived tasks drop off boards and the
  default list but stay editable and unarchivable.
- **Field / ProjectField / TaskFieldValue** — per-workspace catalog of custom
  fields (`Text` / `Textarea` / `Select` / `Version` semver). Projects opt-in
  to fields; their values are persisted per task.
- **Tag / TaskTag** — workspace-wide tag catalog with colors; tags attach to
  tasks many-to-many.
- **SavedView** — per-user named filter set on the workspace-wide tasks grid
  (search / status / assignee / tag / priority / project / sort / page size);
  `User.defaultSavedViewId` selects the view loaded on entry.
- **TaskChecklistItem** — lightweight ordered steps inside a task, each with an
  optional due date and assignee; progress rides on task DTOs as an N-of-M chip.
  Distinct from subtasks (which are real linked `Task` rows).
- **TaskComment** — Markdown comments attributed to the author and tagged
  `Human` or `Agent` (the MCP transport flips actor type via `ActorContext`).
  Supports one-level threaded replies, an "edited" flag, and `@[Name](user:ID)`
  mentions resolved against workspace members.
- **TaskFile** — file attachments stored in the configured S3-compatible
  bucket; metadata persisted alongside the task.
- **TaskRelation** — typed link between two tasks (`Related` / `Duplicates` /
  `Parent` / `DependsOn`); `Parent` relations back the subtask tree.
- **TaskTemplate** — reusable task snapshot (name, description, priority, field
  values, tags) saved from an existing task and instantiated into new ones.
- **TaskWatcher / Notification** — Trello-style task subscriptions plus a
  per-user in-app inbox. Watchers (auto-added on assign / comment / mention, or
  toggled manually) receive assignment, comment, move, mention, and due-soon
  pings; emailable types also go out over SMTP. The topbar bell streams new
  notifications over Mercure.
- **Event** — append-only audit log keyed to workspace / project / task; covers
  task / project / workflow / status / field / tag / priority / comment / file
  / relation / membership / admin actions.

## Roles & permissions

Authorization is centralized in `Kytarna\Service\Auth\PermissionChecker`. Every
mutating controller routes through it.

- **SystemAdmin** — global; passes every `can*` check. Operates on workspaces
  they don't belong to via `/api/admin/*` endpoints (separate frontend at
  `/admin/users` and `/admin/workspaces`). Inside their own workspaces they act
  as a normal member.
- **Owner** — workspace-scoped, one per workspace. Renames / deletes the
  workspace, manages all members, transfers ownership.
- **Admin** — workspace-scoped. Manages members (Member ↔ Admin), invites
  Members, full CRUD on projects, workflows, statuses, custom fields, tags,
  priorities, tasks.
- **Member** — workspace-scoped. Full CRUD on tasks (incl. checklists,
  comments, files, subtasks/relations, tag assignment, watchers, templates,
  saved views); read-only on the rest.

Ownership transfer (`POST /api/workspaces/{id}/transfer-ownership`) is atomic
— the old Owner becomes Admin. Workspace owner removal is blocked; transfer
first.

## Web UI

| Route | Purpose |
|-------|---------|
| `/login`, `/sign-up`, `/forgot-password`, `/reset-password`, `/verify-email`, `/invitations/accept` | Public auth pages |
| `/oauth/authorize` | MCP OAuth consent screen |
| `/onboarding/step-1…3` | First-run wizard for new accounts |
| `/projects` | Project list (workspace-scoped) |
| `/projects/:id/board` | Kanban board with drag-and-drop and task drawer |
| `/projects/:id/workflow` | Workflow editor |
| `/projects/:id/events` | Project activity log |
| `/tasks` | Workspace-wide tasks, as a **table**, **calendar**, or **timeline** — full-text search (Meili), multi-status / assignee / tag / priority / project filters, saved views, sortable columns, pagination, URL-persisted state |
| `/agents` | Agent-vs-human activity stats |
| `/workspaces` | Membership, invitations, tags, priorities, custom fields, MCP clients, events |
| `/settings/scripts`, `/settings/variables` | Sandboxed automation scripts (Monaco editor + run history) and the workspace variable store |
| `/settings` | User account settings (name, locale, theme, password, data export) |
| `/admin/users`, `/admin/workspaces` | SystemAdmin tools |

i18n: EN + CS, switchable from the topbar. Choice is persisted to the user via
`PATCH /api/current-user` so transactional emails arrive in the right
language. Frontend uses `@ngx-translate/core`; backend renders emails via
`TranslatorService` loading `backend/translations/{en,cs}.json`.

## MCP server

Exposed at `POST/GET/DELETE /mcp` over Streamable HTTP (using `mcp/sdk`).
Sessions persist to Redis with a TTL of `MCP_SESSION_TTL` seconds (default
24 h).

**Auth: OAuth 2.1 + PKCE.** Discovery endpoints:

- `GET /.well-known/oauth-authorization-server/mcp`
- `GET /.well-known/oauth-protected-resource/mcp`
- `POST /mcp/oauth/register` — dynamic client registration (open; `redirect_uri` must be `https`, or `http` for loopback)
- `POST /mcp/oauth/authorize` — user approval (requires user JWT)
- `POST /mcp/oauth/token` — code/refresh-token exchange (open)
- `GET /mcp/oauth/client-info` — display name lookup (open)

401 responses include `WWW-Authenticate: Bearer resource_metadata="…"` per
RFC 9728. PKCE `S256` only; no client secret. Access token TTL 1 h, refresh
30 d. Tokens are stored as SHA-256 hashes in `oauth_clients` and
`oauth_authorizations`.

Auto-discovered tools (`backend/src/Mcp/Tool/`):

- `ProjectTools` — list / find / get / create / delete projects.
- `WorkflowTools` — list / find / create / update / move / delete workflow
  statuses.
- `TaskTools` — list / find / get / create / update / move / archive /
  unarchive / duplicate / delete tasks (move accepts `statusId` *or*
  `statusName`), plus `bulk_update_tasks` for batched move / tag / untag /
  assign / priority / delete operations.
- `FieldTools` — manage the workspace's custom-field catalog and per-project
  attachments.
- `TagTools` — list / find / create / update / delete tags, plus
  `set_task_tags` to replace the tag set on a task.
- `PriorityTools` — list / find / create / update / delete workspace
  priorities.
- `MemberTools` — list / find workspace members, invite new ones.
- `SearchTools` — `search_tasks`: typo-tolerant full-text search across task
  names, descriptions, comments, text custom-field values, and tag names
  (Meilisearch-backed).
- `TaskCommentTools` — list, add (threaded replies + `@[Name](user:ID)`
  mentions, agent-tagged automatically), and author-only edit comments.
- `TaskChecklistTools` — list / add / update / toggle / delete checklist items.
- `TaskRecurrenceTools` — get / set / clear a task's recurrence rule
  (cadence, interval, cron, end condition); `create_task` also accepts an
  inline `recurrence` payload.
- `TaskFileTools` — list / attach (base64) / fetch / delete task files.
- `TaskRelationTools` — list / link / unlink typed task relations, plus
  `create_subtask` (create + `Parent`-link in one call).
- `TaskTemplateTools` — list templates, save a task as a template, create a
  task from a template.
- `EventTools` — read the workspace audit log (`list_events`) and a single
  task's history (`list_task_events`).
- `ScriptTools` — list / get / create / update / delete / run sandboxed
  automation scripts and read their run history.

All MCP tools are scoped to the calling user's `currentWorkspace`. SystemAdmins
must use the web admin UI for cross-workspace work. Per-workspace MCP-client
inventory is exposed at `GET /api/workspaces/{id}/mcp-clients`, and
agent-vs-human activity ratios at `GET /api/workspaces/{id}/agent-stats`.

## Realtime

`Service\Realtime\RealtimePublisher` pushes board and task changes to a
Mercure hub. Subscriber JWTs are issued as cookies (`MercureCookieIssuer`) on
authentication / workspace switch; publisher tokens are minted per request.
Set `MERCURE_PUBLISHER_JWT_KEY` and `MERCURE_SUBSCRIBER_JWT_KEY` to enable —
when either is unset the boot guard wires `NullMercureHub` and the rest of
the app keeps working without push updates.

## Search & async jobs

Full-text search is powered by **Meilisearch**. `Service\Search\SearchIndexer`
ships task documents (name, description, comments, text/textarea custom-field
values, tag names) per workspace; `MeiliClient` exposes search both to the
`/api/search` endpoint used by `/tasks` and to the `search_tasks` MCP tool.
Reindex jobs flow through **RabbitMQ** via `Service\Queue\QueuePublisher` so
writes don't block on the search hop. **Redis** holds rate-limit counters,
MCP session state, and other hot caches; **Memcached** is wired in as a
secondary backend (see `CacheFactory`).

## Scripts (sandboxed automations)

Workspaces can run custom JavaScript to automate task flows. Scripts execute in
a hardened **V8 sandbox** — the PHP **`ext-v8js`** extension — inside a dedicated
**script-worker** process, never in the web tier, so the heavyweight V8 runtime
stays out of FrankenPHP and the main AMQP consumer.

- **Triggers.** `Manual` (run button / API), `Scheduled` (5-field cron, ticked
  every minute by the backend container's built-in supercronic cron), or
  `Event` (subscribe to task events; the
  payload is exposed as `kytarna.context.event`).
- **Host API.** A typed `kytarna.*` global: `tasks` (list / get / create / update /
  move / delete / setTags / addComment), `projects`, `workflow(projectId)`,
  `vars` (workspace key/value
  store; secrets encrypted at rest with AES-256-GCM and redacted from logs),
  `log`, `fetch`, and `context`.
- **Per-run limits.** 5 s CPU, 64 MB memory, 20 `fetch` calls, 200 task-API
  calls, no filesystem. Every run records status, duration, logs, error, and
  fetch / task-API call counts in the run history.
- **Editor.** In-app Monaco editor at `/settings/scripts` with `kytarna.*`
  autocomplete, an API reference panel, trigger config, and an output /
  problems / run-history console. Managing scripts requires workspace Admin
  (`canManageScripts`); the same surface is available to agents via `ScriptTools`.

### v8js dependency

`ext-v8js` is a thread-safe (ZTS) extension matching FrankenPHP's embedded ZTS
PHP. The prebuilt `.so` is pulled from the
[`marekskopal/php-v8js`](https://hub.docker.com/r/marekskopal/php-v8js) image in
`backend/Dockerfile` and loaded **only** by the script-worker (via
`php -d extension=v8js.so` in supervisord) — the web and queue processes never
load V8. Self-hosters get it automatically from the published image; no host
install is required.

## Project layout

```
proxy/      nginx reverse proxy (/api/* → backend, /mcp → backend, /* → frontend)
backend/    FrankenPHP + PHP 8.5
  src/
    Controller/       HTTP endpoints (attribute-routed via marekskopal/router)
    Dto/              Wire-level DTOs for requests / responses
    Model/Entity/     ORM entities + enums
    Model/Repository/ Repository classes (+ Enum/ for query enums)
    Service/          Auth, providers, request, translator, realtime, storage,
                      search (Meili), queue (RabbitMQ), cache (Redis/Memcached), etc.
    Mcp/              MCP tools, DTOs, user context
    OAuth/            OAuth 2.1 + PKCE flow for MCP clients
    Middleware/       Authorization, CORS, error handler
    PhpStan/          Custom PHPStan extension for ORM property semantics
  migrations/         marekskopal/orm-migrations
  translations/       en.json, cs.json — backend (email) strings
  tests/              PHPUnit
frontend/   Angular 22 SPA
  src/app/
    authentication/   Login, sign-up, password reset, email verification,
                      Google sign-in
    onboarding/       3-step first-run wizard
    projects/         Project list + CRUD
    board/            Kanban board + task drawer (checklists, subtasks, tags,
                      comments, files, relations, watchers, templates)
    workflow-editor/  Workflow + status editing
    tasks/            Workspace-wide tasks: table, calendar, timeline + saved views
    events/           Activity log
    workspaces/       Workspace management, invitations, tags, priorities,
                      custom fields, MCP clients
    agents/           Agent activity stats
    scripts/          Sandboxed automation scripts (Monaco editor, variables, runs)
    admin/            SystemAdmin pages
    invitations/      Invitation accept flow
    oauth/            MCP OAuth consent screen
    settings/         User account settings
    services/         API clients
    models/           TypeScript interfaces
    shared/components/ Layout, alert, pagination
    core/             Guards, interceptors
  src/assets/brand/   Logo marks + wordmarks (SVG)
  src/i18n/           en.json, cs.json — frontend strings
  src/styles/         SCSS design tokens + mixins
log/        Backend log mount
```

## Common commands

| Command | What it does |
|---------|--------------|
| `make up` | Build & start the full stack |
| `make down` | Stop the stack |
| `make logs` | Tail container logs |
| `make migrate` | Run database migrations |
| `make test` | All tests (backend + frontend + e2e) |
| `make test-backend` | PHPUnit only |
| `make test-frontend` | Vitest only |
| `make test-e2e` | Playwright (boots the docker stack via webServer) |
| `make test-e2e-ui` | Playwright UI mode |
| `make lint` | PHPStan (max) + PHPCS |
| `make lint-fix` | phpcbf auto-fix |
| `make install` | `composer install` + `pnpm install` on host |
| `docker compose --profile dev up -d` | Stack + Adminer at the proxy |

### Direct frontend commands

From `frontend/`:

```bash
pnpm start         # ng serve (proxies API via dev server config)
pnpm build         # production build
pnpm test          # vitest run
pnpm run lint      # ng lint --max-warnings=0
```

### Direct backend commands

From `backend/`:

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/phpcs
vendor/bin/phpcbf
php bin/console migration:run
```

## Linting

- **Backend**: PHPStan at `max` level with `bleedingEdge.neon` + strict /
  deprecation / phpunit / shipmonk / cognitive-complexity / unused-public
  rules. PHPCS uses the slevomat ruleset (tabs, single-line method signatures
  ≤ 140 chars). A custom PHPStan extension
  (`Kytarna\PhpStan\OrmReadWritePropertiesExtension`) marks `#[Column]` /
  `#[ManyToOne]` / `#[ColumnEnum]` properties as ORM-managed (always read,
  always written, always initialized).
- **Frontend**: angular-eslint + `@typescript-eslint`, `simple-import-sort`,
  `unused-imports`. `pnpm run lint` enforces zero warnings.

## Environment variables

| Variable | Purpose |
|----------|---------|
| `APP_ENV` | `development` (default) or `production`. `production` rejects default MYSQL/S3/Meili credentials and short secrets at boot |
| `PROXY_PORT` / `PROXY_PORT_SSL` / `PROXY_SSL_CERT` / `PROXY_SSL_KEY` | Host ports & optional TLS cert for the nginx proxy |
| `MYSQL_*` | Database credentials (rotate from defaults before `APP_ENV=production`) |
| `AUTHORIZATION_TOKEN_KEY` | 32-char secret used to sign JWTs. Generate with `openssl rand -hex 32`; boot fails on the placeholder |
| `GOOGLE_CLIENT_ID` | Google OAuth client ID for the "Sign in with Google" button (leave blank to disable) |
| `MERCURE_PUBLISHER_JWT_KEY` / `MERCURE_SUBSCRIBER_JWT_KEY` / `MERCURE_PUBLISH_URL` | Mercure realtime hub. Generate keys with `openssl rand -hex 32`; unset keys disable realtime gracefully |
| `S3_ACCESS_KEY` / `S3_SECRET_KEY` / `S3_BUCKET` / `S3_ENDPOINT` / `S3_REGION` / `S3_USE_PATH_STYLE` | Object-storage credentials for task file attachments (rotate from `minioadmin` before `APP_ENV=production`) |
| `TASK_FILE_MAX_SIZE_MB` | Maximum per-file upload size for task attachments |
| `REDIS_HOST` / `REDIS_PORT` / `REDIS_PASSWORD` | Redis used for sessions, rate limits, MCP session storage |
| `MEMCACHED_HOST` / `MEMCACHED_PORT` | Memcached (secondary cache backend) |
| `RABBITMQ_HOST` / `RABBITMQ_PORT` / `RABBITMQ_USER` / `RABBITMQ_PASSWORD` / `BACKEND_AMQP_CONSUMER_PREFETCH` | RabbitMQ broker for async jobs (search indexing, etc.) |
| `MEILI_HOST` / `MEILI_PORT` / `MEILI_MASTER_KEY` / `MEILI_INDEX_PREFIX` | Meilisearch instance backing `/api/search` and the `search_tasks` MCP tool |
| `MCP_SESSION_TTL` | TTL (seconds) for persisted MCP sessions in Redis (default 86400) |
| `RATE_LIMIT_LOGIN_ATTEMPTS` / `RATE_LIMIT_LOGIN_BACKOFF_CAP_SECONDS` / `RATE_LIMIT_INVITATIONS_PER_HOUR` | Login + invitation throttling |
| `BACKEND_FRANKENPHP_WORKERS` | FrankenPHP worker count |
| `BACKEND_CORS_ALLOWED_ORIGIN` | Allowed Origin(s) for `/api/*` and Mercure. `*` for dev; with `APP_ENV=production` an explicit space- or comma-separated list is required |
| `BACKEND_LOG_LEVEL` | `development` / `production` |
| `SMTP_HOST` / `SMTP_PORT` / `SMTP_USER` / `SMTP_PASSWORD` | Outbound mail |
| `EMAIL_FROM` | Sender used by invitation, verification, and password-reset emails |
| `APP_URL` | Base URL embedded in email links |
| `ADMINER_USER` / `ADMINER_PASSWORD` | Basic-auth for the optional Adminer profile |

The `dev` Compose profile (`docker compose --profile dev up -d`) boots
`mailpit`, which captures local invitations at the SMTP layer instead of
sending them, plus Adminer behind the proxy for ad-hoc DB inspection. Neither
runs in a plain `docker compose up`, so a production host never starts an
accept-anything SMTP sink.

## Contributing

PRs welcome — see [CONTRIBUTING.md](CONTRIBUTING.md) for local setup, lint /
test commands, code-style expectations, and the PR flow.

## License

[MIT](LICENSE)
