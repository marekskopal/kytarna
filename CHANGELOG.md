# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2026-07-03

### Fixed

- Scheduled automation scripts, due-date reminders, and recurring-task
  spawning now actually run: the backend container ships a built-in
  supercronic cron (`scripts:tick` every minute, `notifications:due-tick` and
  `recurring-tasks:tick` hourly) instead of relying on a host cron that
  operators had to install by hand.
- MCP `list_scripts` / `get_script` (and script create/update responses) now
  report the real `runCount` and `lastStatus` instead of always `0` / `null`.
- DEPLOY.md worker-restart instructions now use `pkill` (supervisord respawns
  the process); the documented `supervisorctl restart` command never worked.

## [1.0.0] - 2026-07-03

First public release. Ukolio is a minimalistic, multi-tenant project & task
manager where AI agents (over MCP) and humans (over the web UI) are equal
first-class actors.

### Added

- **Projects, tasks & views** — projects with customizable per-project
  workflows (statuses), tasks rendered as board, table, calendar, and timeline;
  priorities, tags, custom fields, due/start dates, and task archiving.
- **Task collaboration** — markdown descriptions, threaded comments with
  `@mentions`, checklists, subtasks and typed task relations, file attachments,
  duplication, and reusable task templates.
- **Recurring tasks** — daily/weekly/monthly/cron cadences with a single-carrier
  invariant and hybrid (event- and cron-driven) spawning.
- **Multi-tenancy & roles** — workspaces with Owner/Admin/Member roles,
  invitations, ownership transfer, and a SystemAdmin surface for cross-workspace
  administration.
- **Notifications** — in-app notification center with a realtime bell (Mercure),
  task watchers, and email notifications for mentions, assignments, and
  due-date reminders.
- **MCP server** — OAuth 2.1 + PKCE-secured Streamable HTTP transport exposing
  the full task/project surface plus search, events, and a sandboxed automation
  scripting layer, so agents can plan, create, move, and close work.
- **Automation scripts** — admin-authored JavaScript automations running in a
  V8 sandbox, triggered on events or a cron schedule.
- **Search** — typo-tolerant full-text search across tasks (Meilisearch).
- **Realtime** — live board/task updates and notification pings via a Mercure
  hub.
- **Internationalization** — English and Czech, on both the web UI and
  transactional emails.
- **Authentication** — email/password and Google sign-in for the web UI, with
  email verification and password reset.

### Security

- OAuth dynamic client registration now rejects non-`https` `redirect_uri`
  values (loopback `http` still allowed), closing a stored-XSS vector in the app
  origin; the frontend authorize flow additionally guards the redirect sink.
- The V8 automation-script worker now runs as an unprivileged user instead of
  root, so a sandbox escape no longer lands with full container privileges.
- Logout now expires the HttpOnly Mercure subscriber cookie, preventing a
  subsequent user on a shared browser from resuming the previous session's
  realtime stream.

### Fixed

- Malformed or invalid request bodies (bad JSON, missing/mistyped fields) now
  return `400 Bad Request` instead of `500`, and are logged at warning rather
  than error level.

[1.0.1]: https://github.com/marekskopal/ukolio/releases/tag/v1.0.1
[1.0.0]: https://github.com/marekskopal/ukolio/releases/tag/v1.0.0
