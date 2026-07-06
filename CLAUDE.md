# Kytarna

A multi-tenant guitar-learning platform. Teachers build **Courses** of
**Lectures** (songs, exercises, techniques) and standalone **Songs**; **Students**
join a teacher's workspace and track their own **practice progress**. Every
lecture and song carries guitar tablature (**alphaTex**, rendered with alphaTab)
and a fixed learning status (**To Learn → Learning → Mastered**). AI agents (over
MCP) and humans (over the web UI) are equal first-class actors: both can create
courses, add lectures, log practice, and move work across statuses.

## Services

- `proxy/` — nginx reverse proxy (`/api/*` → backend, `/mcp` → backend, `/` → frontend)
- `backend/` — FrankenPHP + PHP 8.5 + `marekskopal/orm` +
  `marekskopal/router` + MariaDB. Root namespace `Kytarna\`.
- `frontend/` — Angular 22 (standalone components + signals), SCSS design
  tokens (`frontend/src/styles/_variables.scss` + `_mixins.scss`), the warm
  "woodshed" palette (Instrument Serif / Hanken Grotesk / Space Mono). No Tailwind.
  Guitar tablature is rendered client-side by `@coderline/alphatab`.
- `tab-service/` — small stateless Node 24 microservice (`node:http`, no
  framework; single dep `@coderline/alphatab`) the backend calls internally to
  **validate alphaTex** (`POST /validate`) and **convert Guitar Pro files to
  alphaTex** (`POST /convert`). It returns JSON only — no rendering.

## Domain

Every entity extends `AEntity` (`id`, `createdAt`, `updatedAt`). Entities live in
`backend/src/Model/Entity/`, enums in `backend/src/Model/Entity/Enum/`.

- `Workspace` (owner, name, isPublic, joinCode?, description?) — top-level tenant. `isPublic` lists it in the public teacher directory; `joinCode` lets a student self-join.
- `WorkspaceUser` (workspace, user, role ∈ **Teacher/Student**) — membership. Exactly one Teacher (the owner) per workspace; Students are learners.
- `Invitation` (workspace, inviter, email, tokenHash, role, expiresAt, acceptedAt?) — pending email invites (expire after 7 days).
- `User` (email, password? [null for Google-only], name, locale ∈ En/Cs, theme ∈ System/Light/Dark, currentWorkspaceId?, systemRole ∈ User/SystemAdmin, emailVerified, onboardingCompletedAt?, googleId?, defaultSavedViewId?, failedLoginAttempts, tokenVersion, lockedUntil?). `currentWorkspaceId` scopes every web request.
- `Course` (workspace, name, prefix [≤16 chars], description?) — mints `PREFIX-N` codes for its lectures. **No `Workflow`/`Status` entities** — status is the fixed `LearningStatusEnum`.
- `Lecture` (course, status, name, description? [markdown], position, sequenceNumber, tuning?, capo?, targetTempoBpm?, difficulty? ∈ Beginner/Intermediate/Advanced, createdByAgent, archivedAt?). `sequenceNumber` + course `prefix` = a stable public code (e.g. `MP-3`) used in URLs and MCP `get_lecture`. Guitar metadata (tuning/capo/target BPM/difficulty) lives on the lecture. Archived lectures drop off boards and the default list but stay editable/unarchivable.
- `Song` (workspace, status, name, position, course?, sequenceNumber?, description?, tuning?, capo?, targetTempoBpm?, difficulty?, authorName?, albumName?, coverImageKey?, coverImageMimeType?, createdByAgent, archivedAt?) — workspace-level library item. Standalone (no course, no sequenceNumber) or attached to a course, where it appears on that course's board like a lecture and gets a `PREFIX-N` code.
- `LearningStatusEnum` — **fixed**, replaces the old workflow: `ToLearn` ("To Learn") → `Learning` → `Mastered`. Declaration order is significant (MySQL ENUM sorts by index); `Mastered` is terminal ("active" = not yet Mastered). `label()` + `fromLoose()` parse either the enum value or the human label, case-insensitive.
- **Progress is two-layered.** `LectureBoardStatus` / `SongBoardStatus` (user, lecture/song, status) is a per-user board-column overlay — the lecture/song's own `status` is the teacher-authored default/template; the per-user row is what actually drives the column the viewer sees. `ProgressStatusProvider` resolves: per-user row if present, else fall back to the entity's `status`. `ProgressEntry` / `SongProgressEntry` (lecture/song, user, practicedAt [date], note?, tempoBpm?, durationMinutes?) is the dated practice log; `get_practice_summary` aggregates totals, per-week counts, and the BPM trend.
- `Tab` / `SongTab` (parent, name, alphatexContent [text], sourceType ∈ Authored/ImportedGp, originalFile?, tempo?, tuning?, trackCount?) — tablature stored as alphaTex. Create/update validate the alphaTex via `tab-service`; Guitar Pro imports convert via `tab-service`.
- `LectureLink` / `SongLink` (parent, url, kind ∈ Youtube/Other [both use `LectureLinkKindEnum`], label?, timestampSeconds?) — reference links.
- `LectureFile` / `SongFile` (parent, filename, mimeType, size, storageKey, uploadedBy?, uploadedByAgent) — S3-stored attachments.
- `Tag` (workspace, name, color) + join tables `LectureTag` / `SongTag`.
- `LectureWatcher` / `SongWatcher` (parent, user) — Trello-style subscriptions; auto-added on assign/mention, togglable.
- `Notification` (user [recipient], workspaceId, type ∈ **LectureMoved**, lectureId?, courseId?, actorId?, actorName?, data [JSON], readAt?) — per-user in-app inbox. Denormalized ints (not FKs) so a notification survives its lecture; `data` holds lectureCode/lectureName/statusName rendered by the frontend via i18n.
- `SavedView` (workspace, user, name, filterConfig [JSON]) — per-user named filter set on the lectures grid; `User.defaultSavedViewId` selects the one loaded on entry.
- `Event` (author?, type, metadata [JSON], course?, workspaceId?, lectureId?, actorType ∈ Human/Agent, mcpClientId?, mcpClientName?) — append-only audit log. `actorType` + `mcpClient*` are set by `ActorContext`, which `McpController` flips to `Agent` after OAuth-token validation. `EventTypeEnum` covers course/lecture/song/tag/file/member/admin actions (e.g. `LectureMoved`, `SongAddedToCourse`, `MemberJoined`).
- Auth tokens: `EmailVerificationToken`, `PasswordResetToken`, `OAuthClient` (`oauth_clients`), `OAuthAuthorization` (`oauth_authorizations`, tokens stored as SHA-256 hashes, refresh-token rotation via `familyId`).

**No workspace is auto-created on sign-up.** `AuthenticationController::actionPostSignUp`
creates only the `User` and sends email verification; the onboarding wizard then
lets the user either create their own workspace (becoming its **Teacher**) or join
a teacher's workspace as a **Student** (by public directory or join code).
`WorkspaceProvider::createWorkspace` adds the owner as `Teacher`; `joinAsStudent`
adds a `Student`. Inviting a member sends an email via Symfony Mailer (SMTP env:
`SMTP_HOST/PORT/USER/PASSWORD`, `EMAIL_FROM`); `mailpit` captures it locally (dev profile).

## Roles & permissions

Authorization is centralized in `Kytarna\Service\Auth\PermissionChecker`
(interface + impl). Every mutating controller and the SystemAdmin endpoints
route their decisions through it.

- **SystemAdmin** (`User.systemRole`): global; passes every `can*` check. Operates on workspaces they don't belong to via dedicated `/api/admin/*` endpoints (see `Kytarna\Controller\Admin\`) with a separate frontend at `/admin/users` and `/admin/workspaces`. Inside their own workspaces they act as a normal member.
- **Teacher** (workspace-scoped, one per workspace = the owner): creates and edits all content (courses, lectures, songs, tabs, links, files, tags), manages members, renames/deletes the workspace. The Teacher can't be removed or leave — the workspace must be deleted instead (no ownership transfer).
- **Student** (workspace-scoped): read-only on content; tracks their **own** practice progress (per-user board status + practice log) and manages their own watchers/saved views.

The first SystemAdmin is provisioned out-of-band via
`docker compose exec backend php bin/console admin:create` (see DEPLOY.md).
MCP tools remain scoped to `currentWorkspace` — sysadmins must use the web
admin UI for cross-workspace management.

## HTTP API surface

All routes are a string-backed enum in `Kytarna\Route\Routes`; HTTP methods are
declared with `#[RouteGet/RoutePost/...]` attributes on controllers in
`backend/src/Controller/` (auto-discovered by `marekskopal/router`). Highlights:

- `GET /api/health`
- `POST /api/authentication/{login,logout,sign-up,refresh-token,request-password-reset,confirm-password-reset,verify-email,google-login}`, `GET /api/authentication/google-client-id`.
- `GET/PATCH/DELETE /api/current-user`, plus `/password`, `/resend-verification`, `/onboarding-complete`, `/export`.
- `GET/POST /api/workspaces`, `PUT/DELETE /api/workspaces/{id}`, plus `/switch`, `/discover` (public directory), `/join` (by code) + `/{id}/join`, `/{id}/rotate-join-code`, `/{id}/members[/{userId}]`, `/{id}/mcp-clients[/{clientId}/revoke]`, `/{id}/tags`, `/{id}/invitations`, `/{id}/events`, `/{id}/agent-stats`, `/{id}/saved-views`.
- `GET/POST/PUT/DELETE /api/invitations/...`, plus `/lookup`, `/accept`.
- `GET/POST /api/courses`, `GET/PUT/DELETE /api/courses/{id}`, plus `/board`, `/lectures`, `/events`, `/practice-summary`.
- `GET /api/lectures` (workspace-wide, filterable — incl. by tuning), `POST /api/lectures/bulk`, `GET/PUT/DELETE /api/lectures/{id}` (id = numeric **or** code like `MP-3`), `PUT /api/lectures/{id}/move`, `POST /api/lectures/{id}/{archive,unarchive}`.
- Lecture sub-resources: `/api/lectures/{id}/files[/{fileId}/content]`, `/watchers` + `/watch`, `/links[/{linkId}]`, `/tabs[/import]` + `GET/PUT/DELETE /api/tabs/{tabId}`, `/progress` + `/practice-summary` + `PUT/DELETE /api/progress/{entryId}`.
- `GET/POST /api/songs`, `GET/PUT/DELETE /api/songs/{id}`, plus `/move`, `/archive`, `/unarchive`, `/course` (attach/detach), `/cover` (POST/GET/DELETE), and song mirrors of files/tabs/links/progress/watchers (`/api/song-tabs/{id}`, `/api/song-progress/{id}`).
- Notifications: `GET /api/notifications` (`?unreadOnly&limit&offset`), `GET /api/notifications/unread-count`, `POST /api/notifications/{id}/read`, `POST /api/notifications/read-all`, `DELETE /api/notifications/{id}`.
- `PUT/DELETE /api/saved-views/{id}`.
- Admin: `GET /api/admin/users`, `PATCH/DELETE /api/admin/users/{id}`; `GET /api/admin/workspaces[/{id}]`, `PATCH/DELETE /api/admin/workspaces/{id}`, plus `/members[/{userId}]`.
- MCP: `POST/GET/DELETE /mcp`, OAuth discovery + flow endpoints (see below).

Query enums live under `backend/src/Model/Repository/Enum/`
(`OrderDirectionEnum`, `LectureOrderByEnum`, `ArchivedFilterEnum`).

## Frontend routes

Public / guest: `/login`, `/sign-up`, `/forgot-password`, `/reset-password`,
`/verify-email`, `/invitations/accept`, `/oauth/authorize` (MCP consent).

Onboarding (`AuthGuard` + `OnboardingGuard`, `OnboardingShellComponent`):
`/onboarding/step-1…3` — choose Teacher (create workspace) or Student (join),
invite members, then MCP setup.

Main app (`AuthGuard`, `LayoutComponent`):

- `/courses` — the **Library** (course list; nav label is "Library"). `/courses/new`, `/courses/:id/edit`, `/courses/:id/board` (Kanban of lectures/songs across To Learn / Learning / Mastered), `/courses/:id/lectures/new`, `/courses/:id/lectures/:lectureId` (full-page `LecturePageComponent`, **not** a drawer), `/courses/:id/events`.
- `/lectures` — workspace-wide lectures grid (`LecturesGridComponent`, filters + saved views).
- `/songs`, `/songs/new`, `/songs/:id` (`SongPageComponent`).
- `/agents` — agent-vs-human activity stats.
- `/workspaces` — membership, invitations, tags, MCP clients.
- `/settings` — account (name, locale, theme, password, data export).
- `/admin/users`, `/admin/workspaces` — SystemAdmin only (`SystemAdminGuard`).

Feature dirs under `frontend/src/app/`: `board/` (course board + `lecture-page`,
`lecture-card`, `song-card`, `lecture-tabs`, `lecture-links`, `lecture-progress`,
and the guitar-tab `tab-viewer` / `tab-editor`), `courses/`, `lectures/`, `songs/`,
`agents/`, `events/`, `workspaces/`, `onboarding/`, `settings/`, `admin/`,
`authentication/`, `invitations/`, `oauth/`, `services/`, `models/`, `core/`
(guards + interceptors), `shared/` (layout, alert, brand-logo, markdown-editor,
notification-bell, pagination; `status-label.pipe`).

### alphaTab / tablature

`services/alphatab.service.ts` (`AlphaTabService`) dynamically imports
`@coderline/alphatab` (kept out of the initial bundle and jsdom tests) and renders
alphaTex read-only (`enablePlayer: false`), loading fonts from
`assets/alphatab/font/`. `board/tab-viewer.component.ts` renders; `tab-editor.component.ts`
authors alphaTex (validated via `services/tab.service.ts` → backend → tab-service)
with a live preview. Both operate on a `PracticeParent` (`models/practice-parent.ts`)
— the shared abstraction over a lecture **or** a song that owns tabs/progress.

## i18n

- Backend: `Kytarna\Service\Translator\TranslatorService` loads `backend/translations/{en,cs}.json` (email subjects/bodies per `User.locale`; invitee falls back to the inviter).
- Frontend: `@ngx-translate/core`. JSONs live in `frontend/src/i18n/{en,cs}.json`. `LanguageService` initialises from `?lang=`, then localStorage, then `navigator.language`. `PATCH /api/current-user` syncs the choice so emails arrive in the right language. The topbar has a language switcher.

## Docker

```bash
make up            # docker compose up -d --build (full stack)
make migrate       # apply migrations
docker compose --profile dev up -d   # +adminer +mailpit
```

Always-on services: `proxy`, `frontend`, `backend`, `tab-service`, `db`
(MariaDB 11.8), `redis`, `memcached`, `rabbitmq`, `minio`. Dev-only
(`--profile dev`): `adminer`, `mailpit`. There is **no** Meilisearch, Mercure, or
script-worker/V8 in this stack (those belong to stale docs — ignore any
DEPLOY.md/README references to them until corrected).

## MCP server

Exposed at `POST/GET/DELETE /mcp` (Streamable HTTP, `mcp/sdk`). Server built in
`Kytarna\Mcp\Server\KytarnaServer` (name `kytarna`), tools auto-discovered from
`backend/src/Mcp/Tool/`. Sessions persist to Redis with TTL `MCP_SESSION_TTL`
(default 86400).

Auth is **OAuth 2.1 with PKCE** (`S256` only; no client secret; access token 1 h,
refresh 30 d, hashed in `oauth_clients`/`oauth_authorizations`). Discovery:

- `GET /.well-known/oauth-authorization-server/mcp`, `GET /.well-known/oauth-protected-resource/mcp`
- `POST /mcp/oauth/register` (dynamic client registration), `POST /mcp/oauth/authorize` (requires user JWT), `POST /mcp/oauth/token`, `GET /mcp/oauth/client-info`

401 responses include `WWW-Authenticate: Bearer resource_metadata="…"` per RFC 9728.

Tools (`backend/src/Mcp/Tool/`):

- `CourseTools` — list/find/get/create/delete courses.
- `LectureTools` — list/find/get/create/update/move/archive/unarchive/delete + `bulk_update_lectures` (move accepts `status` name; list filters incl. by tuning).
- `SongTools` — list/find/get/create/update/move/archive/unarchive/delete + `add_song_to_course`/`remove_song_from_course`.
- `TabTools` / `SongTabTools` — list/get/create/update/delete tabs + `import_gp_file` / `import_song_gp_file` (Guitar Pro → alphaTex, validated via tab-service).
- `ProgressTools` / `SongProgressTools` — create/list/update/delete practice entries + `get_practice_summary` (lecture/course) / `get_song_practice_summary`.
- `LinkTools` / `SongLinkTools` — add/list/delete reference links.
- `TagTools` (`list_workspace_tags`, `find_tag_by_name`, create/update/delete, `set_lecture_tags`) + `SongTagTools` (`set_song_tags`).
- `LectureFileTools` (`list_lecture_files`, `attach_file`, `get_lecture_file`, `delete_lecture_file`) + `SongFileTools` (`attach_song_file`, …).
- `MemberTools` — `list_workspace_members`, `find_member_by_email`, `invite_member`.
- `EventTools` — `list_events` (workspace audit log), `list_lecture_events`.

The MCP surface mirrors the web UI: agents and humans are equal first-class
actors, each able to build courses, add lectures/songs, author tabs, log
practice, and move work.

## Testing

```bash
make test                    # backend + frontend + e2e
make test-backend            # PHPUnit (inside the backend container)
make test-backend-coverage   # +pcov HTML report at backend/.phpunit.cache/coverage-html
make test-frontend           # Vitest (jsdom + @analogjs/vite-plugin-angular)
make test-e2e                # Playwright (boots the docker stack)
make test-e2e-ui             # Playwright UI mode
```

Backend tests boot the full `ApplicationFactory` container against a separate
MariaDB database (`kytarna_test`, auto-created by `tests/bootstrap.php`) and
truncate tables between tests via `IntegrationTestCase`. Helpers in
`backend/tests/Support/`: `AppHarness` (per-suite singleton), `IntegrationTestCase`
(HTTP dispatch + DB reset), `Fixture` (deterministic user/workspace/course/lecture/
song/tab/progress/JWT builders), and `FakeTabServiceClient` (stubs the tab-service
so tests don't need the Node sidecar). `phpunit.xml` scopes coverage to
`src/{Controller,Mcp,Service,OAuth,Validator}`.

Frontend tests use Vitest 4 with jsdom and the AnalogJS Vite plugin (`frontend/vitest.config.ts`,
TestBed bootstrap in `frontend/src/test-setup.ts`). The app is zoneless, so specs
**must not** import `zone.js/testing` — use `provideZonelessChangeDetection()` and
the standard `fixture.detectChanges()` / `await fixture.whenStable()` lifecycle.

- File naming: `*.spec.ts` co-located next to the unit under test.
- Shared TestBed boilerplate lives in `frontend/src/app/testing/test-providers.ts` —
  prefer `commonTestProviders()` (zoneless + router + HTTP testing) and
  `provideTranslateStub()` (covers any template using `TranslatePipe`; the stub
  mirrors the ngx-translate signal-returning `translate(key, params)` API).
- alphaTab is dynamically imported and never loads in jsdom — `AlphaTabService`
  degrades gracefully in tests; assert against the alphaTex source, not rendered SVG.
- ngx-markdown parses asynchronously and writes `innerHTML` from a floating promise
  `whenStable()` doesn't track. Specs reading rendered markdown must flush
  microtasks (`await new Promise(r => setTimeout(r))`) and re-run `detectChanges()`.

End-to-end tests use Playwright (`frontend/playwright.config.ts`). Specs in
`frontend/e2e/` (`courses`, `lectures`, `onboarding`, `workflow`, `workspaces`,
`login`, `sign-up`, `theme`) with page objects under `frontend/e2e/pages/`.

- The `setup` project (`e2e/setup/auth.setup.ts`) signs up a fresh fixture user per
  run and writes `e2e/.auth/user.json` (storage state) + `e2e/.auth/credentials.json`.
- The default `chromium` project reuses that storage state; auth specs opt out via
  `test.use({storageState: {cookies: [], origins: []}})`.
- `webServer` runs `docker compose up -d --build --wait` from the repo root;
  `reuseExistingServer: true` makes it a no-op when `make up` is already running.
  Override with `E2E_BASE_URL=...`; disable auto-up with `E2E_SKIP_WEBSERVER=1`.
- Self-signed dev certs are ignored (`ignoreHTTPSErrors: true`). Credentials default
  to `Test1234!` (override with `E2E_USER_EMAIL` / `E2E_PASSWORD`).

## Linting

Backend uses PHPStan at `max` level (with `bleedingEdge.neon` +
strict/deprecation/phpunit/shipmonk rules + cognitive-complexity +
unused-public) and PHPCS with the slevomat ruleset (tabs, single-line method
signatures ≤140 chars). Custom PHPStan extension
`Kytarna\PhpStan\OrmReadWritePropertiesExtension` marks
`Column`/`ManyToOne`/`ColumnEnum`-attributed properties as ORM-managed
(always read, always written, always initialized).

Frontend uses angular-eslint + `@typescript-eslint`, with `simple-import-sort`
and `unused-imports`. `pnpm run lint` runs with `--max-warnings=0`.

```bash
make lint           # PHPStan + PHPCS
make lint-fix       # phpcbf auto-fix
```
