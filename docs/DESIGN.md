# Guitar App — MVP Design

Personal guitar-learning app: store lectures with tabs, ideas, and practice progress.
Architecture is a copy of **ukolio** (`../ukolio`) with a reduced infra footprint and a
guitar domain model. Every feature is exposed via MCP so agents can create and manage
content.

## Decisions (locked)

- Domain model: `Workspace → Course → Lecture → ProgressEntry`
- **alphaTex is the source of truth for tab content** — agents author tabs as text.
  Uploaded `.gp` files are converted to alphaTex on import (alphaTab ≥ 1.7 ships an
  official `AlphaTexExporter`; importer reads gp3–gp8 + MusicXML).
- Videos: **skipped for MVP** (revisit later; will require presigned S3 URLs + range
  requests, which ukolio's buffered file flow doesn't do).
- Search: no Meilisearch. Metadata lives in MariaDB; `find_*_by_name` tools use LIKE,
  MariaDB FULLTEXT is the future upgrade path.
- Infra kept from ukolio: **FrankenPHP, RabbitMQ, Redis, Memcached, MinIO (S3), MariaDB**.
  Dropped: **Mercure** (no realtime push), **V8js scripts feature**, **Meilisearch**.

## Stack (inherited from ukolio)

- Backend: PHP 8.5, `marekskopal/router` + league/route, `marekskopal/orm` (+ migrations),
  league/container DI, firebase/php-jwt + Google OAuth login, `mcp/sdk` ^0.6 MCP server
  with OAuth 2.1 + PKCE, FrankenPHP under supervisord (frankenphp + amqp-consumer + cron).
- Frontend: Angular 22 SPA (pnpm, Vitest, Playwright) + `@coderline/alphatab` for tab
  rendering (alphaTex and .gp, client-side; synth playback is a later checkbox).
- Layering: Controller → Service/Provider (single source of domain logic) → Entity/Repository.
  MCP tools and REST controllers both call the same `*ProviderInterface` services.

## New service: `tab-service` (the only architectural addition)

Small Node service running alphaTab ≥ 1.7, internal-only on the compose network,
called synchronously from the PHP backend (payloads are KBs; .gp files are small):

- `POST /validate` — alphaTex in → `{ valid, errors[], metadata }` (agents get immediate
  feedback when `create_tab`/`update_tab` submits broken alphaTex)
- `POST /convert` — .gp binary in → `{ alphaTex, metadata }` (title, artist, tempo,
  tunings, tracks)
- `POST /render` (later) — server-side SVG preview/thumbnail

## Entities

Copied verbatim from ukolio: `User`, `Workspace`, `WorkspaceUser` (Owner/Admin/Member),
`Invitation`, `Tag`, `Status` (per-course workflow).

New / renamed:

| Entity | ≈ ukolio | Fields (beyond id/timestamps) |
|---|---|---|
| `Course` | Project | workspace FK, name, description, archived |
| `Lecture` | Task | course FK, status FK, name, description (markdown), position, tuning, capo, targetTempoBpm, difficulty, tags m:n |
| `Tab` | — (new) | lecture FK, name, alphatexContent TEXT, sourceType (authored \| imported_gp), originalFile FK nullable, extracted metadata (tempo, tuning, trackCount) |
| `ProgressEntry` | — (new) | lecture FK, practicedAt, note (markdown), tempoBpm nullable, durationMinutes nullable |
| `LectureFile` | TaskFile | S3-backed attachments: original .gp, PDF, images (audio/video later) |
| `LectureLink` | — (new) | lecture FK, url, label, kind (youtube \| other), timestampSeconds nullable |

Notes:
- Guitar metadata (tuning/capo/tempo/difficulty) are **hard typed columns**, not ukolio
  custom Fields — agents need typed MCP schemas and SQL-queryable facts
  ("what do I play in drop D?"). Ukolio's custom-fields feature is not copied for MVP.
- Per-course Status workflow is **reused from ukolio** — default workflow
  `To Learn → Learning → Mastered` instead of `To Do → In Progress → Done`; gives the
  kanban board and `move_*` semantics for free.
- Original `.gp` files are kept in S3 next to the converted alphaTex — alphaTex may be
  lossy for exotic GP features, agents edit the alphaTex, the original stays for fidelity.
- ProgressEntry is lecture-scoped in MVP (a progress timeline per lecture; course/global
  views are aggregations).

## MCP tools

Same pattern as ukolio: `final readonly` classes in `Mcp/Tool/*`, `#[McpTool]` attributes,
auto-discovery, workspace-scoped via `McpUserContext`, OAuth 2.1 + PKCE, Redis sessions.

- **Workspace/Members** — copy of ukolio (list/invite/find_member_by_email…)
- **CourseTools** — `list_courses`, `get_course`, `create_course`, `update_course`,
  `delete_course`, `find_course_by_name`
- **WorkflowTools** — copy of ukolio status tools, per course
- **LectureTools** — `list_lectures` (filters: course, status, tag, tuning),
  `get_lecture`, `create_lecture`, `update_lecture`, `move_lecture` (by status name),
  `delete_lecture`, `find_lecture_by_name`, `set_lecture_tags`
- **TabTools** — `list_tabs`, `get_tab` (returns alphaTex + metadata),
  `create_tab` / `update_tab` (alphaTex; validated via tab-service, validation errors
  returned to the agent), `delete_tab`, `import_gp_file` (base64 .gp → store original in
  S3 → tab-service converts → Tab row with alphaTex)
- **ProgressTools** — `create_progress_entry`, `list_progress_entries` (lecture / course /
  date range), `update_progress_entry`, `delete_progress_entry`,
  `get_practice_summary` (totals, entries per week, BPM trend per lecture)
- **FileTools / LinkTools** — attach/list/get/delete files (base64 is fine at MVP sizes;
  `TASK_FILE_MAX_SIZE_MB`-style cap), add/remove lecture links

## docker-compose delta vs ukolio

Keep: proxy (nginx), frontend, backend (FrankenPHP + supervisord), mariadb, redis,
memcached, rabbitmq, minio, mailpit, adminer.
Remove: meilisearch, mercure, script-worker (v8js) + Script* cron/queues + SearchReindex.
Add: tab-service (Node).
Queue handlers kept: EmailVerification, Invitation, PasswordReset, Notification.

## Deferred (explicitly out of MVP)

- Videos / audio recordings (needs presigned S3 upload + range playback)
- Fulltext search (MariaDB FULLTEXT on lecture name/description + tab alphaTex)
- alphaTab synth playback in the viewer (mind soundfont licensing)
- Practice scheduling / recurring reminders (ukolio recurring-task machinery ports directly)
- Server-side tab thumbnails, custom fields, realtime updates (Mercure)

## Known risks

- `mcp/sdk` is ^0.6 (pre-1.0) — expect API churn, same exposure as ukolio.
- alphaTex round-trip fidelity for advanced GP8 features — mitigated by keeping originals.
- Frontend has no ukolio precedent for the alphaTab viewer component — new UI work.
