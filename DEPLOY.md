# Deploying Kytarna

## First boot

After bringing the stack up and applying migrations, the database has no
SystemAdmin — you must create one before anyone can manage workspaces or
access `/admin/*`.

```bash
make up                                              # build & start the stack
make migrate                                         # apply migrations

# Interactive (prompts for email + password):
docker compose exec backend php bin/console admin:create

# Non-interactive (e.g. provisioning scripts):
docker compose exec -T backend php bin/console admin:create \
    --email admin@example.com \
    --password "$(openssl rand -base64 24)" \
    --name "Ops"
```

Passwords must be at least 12 characters. Re-run the command later to add
additional SystemAdmins; existing users are detected by email and rejected.

Everyone else signs up at `/sign-up`, verifies their email, and is dropped into
the onboarding wizard where they either create their own workspace (becoming its
**Teacher**) or join a teacher's workspace as a **Student**. No workspace and no
admin are seeded automatically.

## Environment

Required variables — see `.env.example` for the full annotated list:

| Variable | Notes |
|----------|-------|
| `APP_ENV` | Set to `production` on real deployments. The boot guard then rejects the dev defaults for `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD`, `REDIS_PASSWORD`, `S3_ACCESS_KEY`, `S3_SECRET_KEY` (and any secret shorter than 16 characters), and refuses `*` for `BACKEND_CORS_ALLOWED_ORIGIN` |
| `BACKEND_CORS_ALLOWED_ORIGIN` | Allowed Origin(s) for `/api/*`. Space- or comma-separated; production must list explicit origins (no `*`) |
| `AUTHORIZATION_TOKEN_KEY` | ≥ 32 chars; generate with `openssl rand -hex 32`. The boot guard rejects the placeholder value regardless of `APP_ENV` |
| `MYSQL_*` | MariaDB host + credentials |
| `SMTP_*`, `EMAIL_FROM` | Outbound mail (invitations, email verification, password resets) — sent via the async `amqp-consumer` worker, see "Async email delivery" below |
| `S3_*` | Object storage (MinIO in dev) for lecture / song file attachments and song covers |
| `LECTURE_FILE_MAX_SIZE_MB` | Maximum per-file upload size for lecture / song attachments |
| `REDIS_*` | Redis — sessions, rate-limit counters, and the MCP session store |
| `MEMCACHED_*` | Memcached (secondary cache backend) |
| `RABBITMQ_*` | RabbitMQ host/port/user/password used by both the publisher (HTTP request path) and the supervisor-managed `amqp-consumer.php` worker |
| `BACKEND_AMQP_CONSUMER_PREFETCH` | Per-channel `basic_qos` prefetch for the consumer (default `10`) — caps in-flight unacked messages |
| `TAB_SERVICE_URL` | URL the backend uses to reach the `tab-service` (default `http://tab-service:8080`); the service validates alphaTex and converts Guitar Pro files. `TAB_SERVICE_PORT` / `TAB_SERVICE_MAX_BODY_BYTES` configure the service itself |
| `MCP_SESSION_TTL` | TTL (seconds) for persisted MCP/OAuth sessions in Redis (default 86400) |
| `GOOGLE_CLIENT_ID` | Optional — enables "Sign in with Google"; leave blank to disable |

## Background processes

Inside the `backend` container, **supervisor** (`backend/docker/supervisord.conf`)
manages three programs alongside each other:

- `frankenphp` — the web/API server.
- `amqp-consumer` — `backend/src/amqp-consumer.php`, the async email worker (below).
- `cron` — `supercronic` against `backend/docker/cron.d/kytarna`. The crontab
  currently defines **no scheduled jobs**; the program is wired up so future
  scheduled work needs no host cron.

## Async email delivery

Invitation, password-reset, and email-verification emails are published to
RabbitMQ from the HTTP request and sent by a background worker, so SMTP
latency / outages don't block sign-up / invite flows.

- **Publisher**: `Kytarna\Service\Queue\QueuePublisher` (`php-amqplib`), injected
  into the providers. Lazy-connects on first publish per worker.
- **Queues**: `invitation`, `email-verification`, `password-reset` —
  enumerated in `Kytarna\Service\Queue\Enum\QueueEnum`. Messages are durable
  + persistent.
- **Consumer**: `backend/src/amqp-consumer.php`, managed by supervisor inside
  the `backend` container alongside FrankenPHP (see
  `backend/docker/supervisord.conf`). One process consumes all three queues.
- **Retry**: handler exceptions trigger `nack(requeue=true)` — the message
  goes back to the queue and is retried. There is no DLQ; alert on the
  `[program:amqp-consumer]` log stream.
- **Operations**:
  - Tail the worker: `docker compose logs -f backend | grep amqp-consumer`
  - Check queue depth: `docker compose exec rabbitmq rabbitmqctl list_queues`
  - Restart just the worker without bouncing the web process (supervisord
    respawns it): `docker compose exec backend pkill -f amqp-consumer.php`

## Tab service

The `tab-service` container (Node + alphaTab) is a hard dependency of the
backend — it validates alphaTex notation and converts uploaded Guitar Pro files
to alphaTex. `docker compose` starts it automatically and the backend waits on
its health check; the backend reaches it at `TAB_SERVICE_URL`. It is stateless,
so no volume or migration is involved — just keep it running.

## SSL termination

The default `docker-compose.yml` exposes plain HTTP on `${PROXY_PORT}`. To
terminate TLS at the proxy, layer on `docker-compose.ssl.yml`:

```bash
docker compose -f docker-compose.yml -f docker-compose.ssl.yml up -d
```

Requires `PROXY_HOST`, `PROXY_PORT_SSL`, `PROXY_SSL_CERT`, `PROXY_SSL_KEY`.
