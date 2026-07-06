<p align="center">
  <img src="frontend/src/assets/brand/logo-wordmark-inverse.svg" alt="Kytarna" width="320" />
</p>

<p align="center">
  <strong>Learn guitar in the open — courses, lectures, and tablature your <em>agents</em> can build with you.</strong>
</p>

<p align="center">
  A multi-tenant guitar-learning platform built around the <a href="https://modelcontextprotocol.io">Model Context Protocol</a>.
  Teachers publish courses of lectures and songs; students join and track their own practice.
  Every lecture carries guitar tablature (alphaTex, rendered with <a href="https://alphatab.net">alphaTab</a>), and Claude, Cursor,
  ChatGPT, or any MCP client can create courses, add lectures, author tabs, and log practice alongside you.
  Self-hostable, MIT-licensed, EN + CS.
</p>

<p align="center">
  <a href="https://www.kytarna.com">www.kytarna.com</a> · MCP endpoint: <code>https://www.kytarna.com/mcp</code>
</p>

---

## Why Kytarna

- **Guitar-native.** Lectures and songs store tablature as **alphaTex** and render it in the browser with **alphaTab** — no images, no PDFs. Import a **Guitar Pro** file and it's converted to editable notation.
- **Teacher / Student workspaces.** A workspace owner is the **Teacher** who authors courses, lectures, and songs; **Students** join (by public directory or a join code) with read-only content and their **own** practice tracking.
- **Real progress, per learner.** A fixed **To Learn → Learning → Mastered** status per lecture/song, overlaid per student, plus a dated **practice log** (tempo in BPM, minutes) with per-week counts and a BPM trend.
- **MCP-native.** Streamable HTTP transport, session persistence, tools auto-discovered from the backend. Agents create courses, add lectures, author/validate tabs, and log practice.
- **OAuth 2.1 + PKCE for agents.** No shared API keys — each agent gets its own credential.
- **Human/Agent attribution.** Lectures, songs, files, and events are tagged `Human` or `Agent`; an append-only event log per workspace / course / lecture.
- **Multi-tenant.** Teacher / Student roles, invitations, a public teacher directory, and a separate SystemAdmin tier for global operations.
- **Rich lectures & songs.** Markdown descriptions, tabs, YouTube/reference links, file attachments, tags, watchers, guitar metadata (tuning, capo, target BPM, difficulty), archiving, saved views, and an in-app notification inbox.

## Stack

| Layer      | Tech |
|------------|------|
| Proxy      | nginx |
| Frontend   | Angular 22 (standalone components + signals), SCSS, ngx-translate, [alphaTab](https://alphatab.net) |
| Backend    | FrankenPHP, PHP 8.5, [`marekskopal/orm`](https://github.com/marekskopal/orm), [`marekskopal/router`](https://github.com/marekskopal/router), Symfony Mailer |
| Tab service | Node 24 microservice (`@coderline/alphatab`) — validates alphaTex, converts Guitar Pro → alphaTex |
| Database   | MariaDB 11.8 |
| Cache      | Redis (sessions, rate limits, MCP session store) + Memcached |
| Queue      | RabbitMQ — async jobs (email delivery, etc.) |
| Storage    | S3-compatible (MinIO in dev) for lecture / song file attachments and song covers |
| Mail       | Mailpit (dev) / any SMTP (prod) |
| Auth       | JWT + optional Google sign-in for web, OAuth 2.1 + PKCE for MCP |

## Quick start

```bash
cp .env.example .env                                          # adjust ports / secrets as needed
openssl rand -hex 32                                          # generate one value for AUTHORIZATION_TOKEN_KEY
make up                                                       # build & start the full stack
make migrate                                                  # run database migrations
docker compose exec backend php bin/console admin:create      # bootstrap the first SystemAdmin
open http://localhost:4300/                                   # default proxy port
```

The backend refuses to boot when `AUTHORIZATION_TOKEN_KEY` is missing, shorter
than 32 characters, or still set to the `replace-with-32-char-random-hex-key-here`
placeholder — generate a real value with `openssl rand -hex 32`. With
`APP_ENV=production` the same boot guard also rejects the dev defaults for
`MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD`, `REDIS_PASSWORD`, `S3_ACCESS_KEY`, and
`S3_SECRET_KEY`, and refuses a wildcard `BACKEND_CORS_ALLOWED_ORIGIN` — rotate
them before going live.

`admin:create` prompts for email + password (or accepts `--email`/`--password`/`--name`
for non-interactive provisioning). See [DEPLOY.md](DEPLOY.md) for deployment details.

Anyone can also sign up at `/sign-up`. **No workspace is auto-created** — new
accounts go through email verification and then a 3-step onboarding wizard
(`/onboarding/step-1…3`) that lets them either **create their own workspace**
(becoming its Teacher, or learning solo) or **join a teacher's workspace** as a
Student, via the public directory or a join code. The standard password-reset
loop is wired (`request-password-reset` → `confirm-password-reset`). Setting
`GOOGLE_CLIENT_ID` enables one-click Google sign-in alongside email/password.

## Domain

- **Workspace** — top-level tenant. `isPublic` lists it in the public teacher
  directory; `joinCode` lets a student self-join.
- **WorkspaceUser** — membership with a role: **Teacher** (the workspace owner,
  one per workspace) or **Student** (a learner who joined).
- **Invitation** — pending email invite, signed token, expires after 7 days.
- **User** — `email`, `password` (nullable for Google-only accounts), `name`,
  `locale` (`en` / `cs`), `theme` (`System` / `Light` / `Dark`),
  `currentWorkspaceId`, `systemRole` (`User` / `SystemAdmin`), `emailVerified`,
  `onboardingCompletedAt`, optional `googleId`, `defaultSavedViewId`.
  `currentWorkspaceId` scopes every web request.
- **Course** — workspace-scoped, with a short `prefix` (e.g. `MP`) used to mint
  lecture codes. Groups lectures; there is **no** editable workflow — status is
  fixed (see below).
- **Lecture** — course-scoped, lives in a **learning status**, has name / Markdown
  description / position / guitar metadata (`tuning`, `capo`, `targetTempoBpm`,
  `difficulty`) and a nullable `archivedAt`. `sequenceNumber` + the course's
  `prefix` gives a stable public code (e.g. `MP-3`) used in URLs and MCP
  `get_lecture`. `createdByAgent = true` when created via MCP. Archived lectures
  drop off boards and the default list but stay editable / unarchivable.
- **Song** — workspace-level library item with `authorName` / `albumName` / cover
  image and the same guitar metadata + status as a lecture. Lives standalone in
  the library, or is **attached to a course** (where it appears on that course's
  board like a lecture and gets a `PREFIX-N` code).
- **Learning status** — a **fixed** three-state lifecycle, replacing the old
  editable workflow: **To Learn → Learning → Mastered** (`Mastered` is terminal).
  The lecture/song carries a teacher-authored default status; each student gets a
  **personal overlay** (`LectureBoardStatus` / `SongBoardStatus`) that drives the
  board column they actually see.
- **ProgressEntry / SongProgressEntry** — dated practice log per lecture/song and
  user (`practicedAt`, `note`, `tempoBpm`, `durationMinutes`). Practice summaries
  aggregate totals, per-week counts, and the BPM trend.
- **Tab / SongTab** — tablature stored as **alphaTex** (`sourceType` = `Authored`
  or `ImportedGp`), with optional tempo / tuning / track count. Create / update
  validate the alphaTex through the tab-service; Guitar Pro imports are converted
  by it.
- **LectureLink / SongLink** — reference links (`Youtube` / `Other`), with an
  optional `label` and video `timestampSeconds`.
- **LectureFile / SongFile** — attachments stored in the configured
  S3-compatible bucket.
- **Tag / LectureTag / SongTag** — workspace-wide tag catalog with colors; tags
  attach to lectures and songs.
- **SavedView** — per-user named filter set on the workspace-wide lectures grid;
  `User.defaultSavedViewId` selects the view loaded on entry.
- **LectureWatcher / SongWatcher + Notification** — Trello-style subscriptions
  plus a per-user in-app inbox; the topbar bell surfaces new notifications.
- **Event** — append-only audit log keyed to workspace / course / lecture; covers
  course / lecture / song / tag / file / membership / admin actions, each tagged
  `Human` or `Agent`.

## Roles & permissions

Authorization is centralized in `Kytarna\Service\Auth\PermissionChecker`. Every
mutating controller routes through it.

- **SystemAdmin** — global; passes every `can*` check. Operates on workspaces they
  don't belong to via `/api/admin/*` endpoints (separate frontend at `/admin/users`
  and `/admin/workspaces`). Inside their own workspaces they act as a normal member.
- **Teacher** — workspace-scoped, one per workspace (the owner). Creates and edits
  all content (courses, lectures, songs, tabs, links, files, tags), manages members,
  invites Students, renames / deletes the workspace.
- **Student** — workspace-scoped. Read-only on content; tracks their **own**
  practice progress and manages their own watchers and saved views.

There is exactly one Teacher (the owner) and no ownership transfer — the Teacher
can't be removed or leave a workspace. To retire one, delete the workspace (or a
SystemAdmin acts on it via `/api/admin/*`).

## Web UI

| Route | Purpose |
|-------|---------|
| `/login`, `/sign-up`, `/forgot-password`, `/reset-password`, `/verify-email`, `/invitations/accept` | Public auth pages |
| `/oauth/authorize` | MCP OAuth consent screen |
| `/onboarding/step-1…3` | First-run wizard: create a workspace (Teacher) or join one (Student), invite members, connect MCP |
| `/courses` | The **Library** — course list (workspace-scoped) |
| `/courses/:id/board` | Kanban board of the course's lectures & songs across To Learn / Learning / Mastered |
| `/courses/:id/lectures/:lectureId` | Full-page lecture detail — tabs, practice log, links, files, watchers |
| `/courses/:id/events` | Course activity log |
| `/lectures` | Workspace-wide lectures grid — filters (status / tag / tuning / …), saved views, sortable columns, pagination |
| `/songs`, `/songs/:id` | Song library and full-page song detail |
| `/agents` | Agent-vs-human activity stats |
| `/workspaces` | Membership, invitations, tags, join code, MCP clients, events |
| `/settings` | Account settings (name, locale, theme, password, data export) |
| `/admin/users`, `/admin/workspaces` | SystemAdmin tools |

The UI is themed in the warm **"woodshed"** palette (cream paper, terracotta
accent, olive "mastered" / gold "agent" hues) with Instrument Serif, Hanken
Grotesk, and Space Mono, and honors a System / Light / Dark theme toggle.

i18n: EN + CS, switchable from the topbar. Choice is persisted to the user via
`PATCH /api/current-user` so transactional emails arrive in the right language.
Frontend uses `@ngx-translate/core`; backend renders emails via `TranslatorService`
loading `backend/translations/{en,cs}.json`.

## MCP server

Exposed at `POST/GET/DELETE /mcp` over Streamable HTTP (using `mcp/sdk`). The
server (`Mcp\Server\KytarnaServer`, name `kytarna`) manages the authenticated
user's guitar-learning content: a workspace holds courses, each course holds
lectures (and attached songs), every lecture/song has a fixed To Learn / Learning
/ Mastered status. Sessions persist to Redis with a TTL of `MCP_SESSION_TTL`
seconds (default 24 h).

**Auth: OAuth 2.1 + PKCE.** Discovery endpoints:

- `GET /.well-known/oauth-authorization-server/mcp`
- `GET /.well-known/oauth-protected-resource/mcp`
- `POST /mcp/oauth/register` — dynamic client registration (open; `redirect_uri` must be `https`, or `http` for loopback)
- `POST /mcp/oauth/authorize` — user approval (requires user JWT)
- `POST /mcp/oauth/token` — code/refresh-token exchange (open)
- `GET /mcp/oauth/client-info` — display name lookup (open)

401 responses include `WWW-Authenticate: Bearer resource_metadata="…"` per
RFC 9728. PKCE `S256` only; no client secret. Access token TTL 1 h, refresh 30 d.
Tokens are stored as SHA-256 hashes in `oauth_clients` and `oauth_authorizations`.

Auto-discovered tools (`backend/src/Mcp/Tool/`):

- `CourseTools` — list / find / get / create / delete courses.
- `LectureTools` — list / find / get / create / update / move / archive / unarchive
  / delete lectures (`move` takes a status name), plus `bulk_update_lectures`
  (batched move / tag / untag / delete). `list_lectures` filters incl. by tuning.
- `SongTools` — list / find / get / create / update / move / archive / unarchive /
  delete songs, plus `add_song_to_course` / `remove_song_from_course`.
- `TabTools` / `SongTabTools` — list / get / create / update / delete tabs, plus
  `import_gp_file` / `import_song_gp_file` (Guitar Pro → alphaTex). Create / update
  validate the alphaTex via the tab-service and return errors if invalid.
- `ProgressTools` / `SongProgressTools` — create / list / update / delete practice
  entries, plus `get_practice_summary` (a lecture or a whole course) /
  `get_song_practice_summary`.
- `LinkTools` / `SongLinkTools` — add / list / delete reference links.
- `TagTools` — list / find / create / update / delete tags, plus `set_lecture_tags`;
  `SongTagTools` adds `set_song_tags`.
- `LectureFileTools` / `SongFileTools` — list / attach (base64) / fetch / delete files.
- `MemberTools` — list / find workspace members, invite new ones.
- `EventTools` — read the workspace audit log (`list_events`) and a single lecture's
  history (`list_lecture_events`).

All MCP tools are scoped to the calling user's `currentWorkspace`. SystemAdmins must
use the web admin UI for cross-workspace work. Per-workspace MCP-client inventory is
at `GET /api/workspaces/{id}/mcp-clients`, and agent-vs-human activity ratios at
`GET /api/workspaces/{id}/agent-stats`.

## Tab service

`tab-service/` is a small, stateless Node 24 microservice (built on `node:http`,
its only dependency `@coderline/alphatab`) that the backend calls over the internal
Docker network. It returns JSON only — the frontend does the rendering.

- `GET /health` — liveness (`{ status, alphaTabVersion }`).
- `POST /validate` — body `{ "alphaTex": "…" }`; returns `{ valid: true, metadata }`
  or `{ valid: false, errors: [{ message, line, col }] }`.
- `POST /convert` — raw Guitar Pro bytes (gp3/gp4/gp5/gpx/gp); returns
  `{ alphaTex, metadata }`, or `422` if the file can't be parsed.

Configured with `TAB_SERVICE_PORT` (default `8080`) and `TAB_SERVICE_MAX_BODY_BYTES`
(default 10 MiB, guarding `.gp` uploads); the backend reaches it at `TAB_SERVICE_URL`.

## Async jobs & cache

Email delivery flows through **RabbitMQ** via the backend's AMQP consumer so web
requests don't block on SMTP. **Redis** holds rate-limit counters, MCP session
state, and other hot caches; **Memcached** is wired in as a secondary backend.

## Project layout

```
proxy/        nginx reverse proxy (/api/* → backend, /mcp → backend, /* → frontend)
backend/      FrankenPHP + PHP 8.5 (namespace Kytarna\)
  src/
    Controller/       HTTP endpoints (attribute-routed via marekskopal/router)
    Route/            Routes enum (single source of paths)
    Dto/              Wire-level DTOs for requests / responses
    Model/Entity/     ORM entities + Enum/
    Model/Repository/ Repository classes (+ Enum/ for query enums)
    Service/          Auth, providers, translator, storage (S3), queue (RabbitMQ),
                      cache (Redis/Memcached), tab-service client, progress, etc.
    Mcp/              MCP server, tools, DTOs, Redis session store, user context
    OAuth/            OAuth 2.1 + PKCE flow for MCP clients
    Command/          Console commands (admin:create, migrations)
    PhpStan/          Custom PHPStan extension for ORM property semantics
  migrations/         marekskopal/orm-migrations
  translations/       en.json, cs.json — backend (email) strings
  tests/              PHPUnit (+ Support/ helpers, incl. FakeTabServiceClient)
tab-service/  Node 24 alphaTex validation + Guitar Pro conversion microservice
frontend/     Angular 22 SPA
  src/app/
    authentication/   Login, sign-up, password reset, email verification, Google sign-in
    onboarding/       3-step first-run wizard (create / join workspace, invite, MCP)
    courses/          Course library + CRUD
    board/            Course board + full-page lecture detail (tabs, progress,
                      links, files, watchers) and the alphaTab tab viewer / editor
    lectures/         Workspace-wide lectures grid + saved views
    songs/            Song library + full-page song detail
    events/           Course activity log
    agents/           Agent activity stats
    workspaces/       Workspace management, invitations, tags, MCP clients
    admin/            SystemAdmin pages
    invitations/      Invitation accept flow
    oauth/            MCP OAuth consent screen
    settings/         User account settings
    services/         API clients (incl. AlphaTabService, TabService)
    models/           TypeScript interfaces (incl. PracticeParent)
    shared/components/ Layout, alert, brand-logo, markdown-editor, notification-bell, pagination
    core/             Guards, interceptors
  src/assets/brand/   Logo marks + wordmarks (SVG)
  src/assets/alphatab/ alphaTab fonts
  src/i18n/           en.json, cs.json — frontend strings
  src/styles/         SCSS design tokens + mixins ("woodshed" palette)
log/          Backend log mount
```

## Common commands

| Command | What it does |
|---------|--------------|
| `make up` | Build & start the full stack |
| `make down` | Stop the stack |
| `make logs` | Tail container logs |
| `make migrate` | Run database migrations |
| `make install` | `composer install` + `pnpm install` on host |
| `make test` | All tests (backend + frontend + e2e) |
| `make test-backend` | PHPUnit only |
| `make test-frontend` | Vitest only |
| `make test-e2e` | Playwright (boots the docker stack) |
| `make test-e2e-ui` | Playwright UI mode |
| `make lint` | PHPStan (max) + PHPCS |
| `make lint-fix` | phpcbf auto-fix |
| `docker compose --profile dev up -d` | Stack + Adminer + Mailpit |

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
  deprecation / phpunit / shipmonk / cognitive-complexity / unused-public rules.
  PHPCS uses the slevomat ruleset (tabs, single-line method signatures ≤ 140
  chars). A custom PHPStan extension
  (`Kytarna\PhpStan\OrmReadWritePropertiesExtension`) marks `#[Column]` /
  `#[ManyToOne]` / `#[ColumnEnum]` properties as ORM-managed.
- **Frontend**: angular-eslint + `@typescript-eslint`, `simple-import-sort`,
  `unused-imports`. `pnpm run lint` enforces zero warnings.

## Environment variables

| Variable | Purpose |
|----------|---------|
| `APP_ENV` | `development` (default) or `production`. `production` rejects default MYSQL/Redis/S3 credentials, short secrets, and a wildcard CORS origin at boot |
| `PROXY_HOST` / `PROXY_PORT` / `PROXY_PORT_SSL` / `PROXY_SSL_CERT` / `PROXY_SSL_KEY` | Host + ports & optional TLS cert for the nginx proxy |
| `MYSQL_*` | MariaDB credentials (rotate from defaults before `APP_ENV=production`) |
| `AUTHORIZATION_TOKEN_KEY` | ≥32-char secret used to sign JWTs. Generate with `openssl rand -hex 32`; boot fails on the placeholder |
| `GOOGLE_CLIENT_ID` | Google OAuth client ID for the "Sign in with Google" button (leave blank to disable) |
| `S3_ACCESS_KEY` / `S3_SECRET_KEY` / `S3_BUCKET` / `S3_ENDPOINT` / `S3_REGION` / `S3_USE_PATH_STYLE` | Object-storage credentials for file attachments & song covers (rotate from `minioadmin` before production) |
| `LECTURE_FILE_MAX_SIZE_MB` | Maximum per-file upload size for lecture / song attachments |
| `REDIS_HOST` / `REDIS_PORT` / `REDIS_PASSWORD` | Redis (sessions, rate limits, MCP session storage) |
| `MEMCACHED_HOST` / `MEMCACHED_PORT` | Memcached (secondary cache backend) |
| `RABBITMQ_HOST` / `RABBITMQ_PORT` / `RABBITMQ_USER` / `RABBITMQ_PASSWORD` / `BACKEND_AMQP_CONSUMER_PREFETCH` | RabbitMQ broker for async jobs (email, etc.) |
| `TAB_SERVICE_URL` / `TAB_SERVICE_PORT` / `TAB_SERVICE_MAX_BODY_BYTES` | The alphaTex/Guitar-Pro tab-service: URL the backend calls, its listen port, and max request body |
| `MCP_SESSION_TTL` | TTL (seconds) for persisted MCP sessions in Redis (default 86400) |
| `RATE_LIMIT_LOGIN_ATTEMPTS` / `RATE_LIMIT_LOGIN_BACKOFF_CAP_SECONDS` / `RATE_LIMIT_INVITATIONS_PER_HOUR` / `RATE_LIMIT_PASSWORD_RESETS_PER_HOUR` | Login, invitation, and password-reset throttling |
| `BACKEND_FRANKENPHP_WORKERS` | FrankenPHP worker count |
| `BACKEND_CORS_ALLOWED_ORIGIN` | Allowed Origin(s) for `/api/*`. `*` for dev; `APP_ENV=production` requires an explicit list |
| `BACKEND_LOG_LEVEL` | `production` (errors + warnings) or `debug` (verbose) |
| `SMTP_HOST` / `SMTP_PORT` / `SMTP_USER` / `SMTP_PASSWORD` / `EMAIL_FROM` | Outbound mail (invitation, verification, password-reset) |
| `ADMINER_USER` / `ADMINER_PASSWORD` | Basic-auth for the optional Adminer profile |

The `dev` Compose profile (`docker compose --profile dev up -d`) boots `mailpit`,
which captures local email at the SMTP layer instead of sending it, plus Adminer
for ad-hoc DB inspection. Neither runs in a plain `docker compose up`.

## Contributing

PRs welcome — see [CONTRIBUTING.md](CONTRIBUTING.md) for local setup, lint / test
commands, code-style expectations, and the PR flow.

## License

[MIT](LICENSE)
