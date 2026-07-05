# Contributing to Ukolio

Thanks for considering a contribution. Ukolio is MIT-licensed and built in the
open. Bug reports, fixes, docs, and features are all welcome.

## Ground rules

- **Open an issue first** for anything non-trivial (new feature, behaviour
  change, dependency bump, architectural touch). A few sentences are enough —
  we can sort out the approach before you spend time writing code.
- **Match the existing style.** Tabs for PHP, 4-space indent for TS/HTML/SCSS
  (see `.editorconfig` if present), no trailing whitespace.
- **Keep PRs focused.** One topic per PR. If you find unrelated cleanups along
  the way, send them in a separate PR.
- **Don't commit secrets.** `.env` is gitignored on purpose — only update
  `.env.example` when adding new variables.

## Setting up locally

You need Docker, `make`, and (for direct backend/frontend commands) PHP 8.5,
Composer, Node 22+, and `pnpm`.

```bash
git clone git@github.com:marekskopal/ukolio.git
cd ukolio
cp .env.example .env
sed -i.bak "s|AUTHORIZATION_TOKEN_KEY=.*|AUTHORIZATION_TOKEN_KEY=$(openssl rand -hex 32)|" .env && rm .env.bak
make up                                                    # build & start the full stack
make migrate                                               # apply database migrations
docker compose exec backend php bin/console admin:create   # bootstrap a SystemAdmin
open http://localhost:4300/
```

See the [README](README.md#quick-start) for more on env vars and first-time
bootstrap, and [DEPLOY.md](DEPLOY.md) for production-deploy details.

## Lint + tests

CI runs on every PR. Run the same checks locally before pushing:

```bash
make lint           # PHPStan (max) + PHPCS
make lint-fix       # phpcbf auto-fix where possible
make test           # backend + frontend + e2e
make test-backend   # PHPUnit only
make test-frontend  # Vitest only
make test-e2e       # Playwright
```

Frontend lint and build directly:

```bash
cd frontend
pnpm install
pnpm run lint       # ng lint --max-warnings=0
pnpm run build      # production build (catches type errors)
pnpm run test       # Vitest
```

Backend tooling runs inside the container so it picks up the PHP 8.5 image,
required extensions, and the test database. Run them directly with:

```bash
docker compose exec backend vendor/bin/phpstan analyse --no-progress
docker compose exec backend vendor/bin/phpcs
docker compose exec -T -e TEST_MYSQL_DATABASE=ukolio_test backend vendor/bin/phpunit
```

## Code style expectations

| Layer | Tool | Bar |
|-------|------|-----|
| Backend | PHPStan | `max` level + `bleedingEdge.neon` + strict / deprecation / phpunit / shipmonk / cognitive-complexity / unused-public rules — **no `@phpstan-ignore`, no baseline entries** |
| Backend | PHPCS | slevomat ruleset (tabs, single-line method signatures ≤ 140 chars). Run `make lint-fix` to auto-fix |
| Frontend | ESLint | `pnpm run lint` enforces `--max-warnings=0` with angular-eslint + `@typescript-eslint` + `simple-import-sort` + `unused-imports` |
| Frontend | Angular | Standalone components + signals, zoneless. Specs use `commonTestProviders()` + `provideTranslateStub()` from `src/app/testing/test-providers.ts` |
| Tests | PHPUnit / Vitest / Playwright | New behaviour ships with tests — at least one happy path + the most plausible failure mode |

If a lint rule actively gets in your way (vs. being a temporary annoyance),
flag it in your PR — we'd rather change the rule than litter the codebase
with suppressions.

## Commit messages

- One line, imperative, ≤ 72 chars on the subject (e.g. `Add Meilisearch
  search index (U-71)`).
- Reference the related issue / task code at the end of the subject or in the
  body (`Fixes #42`, `U-71`).
- Body (optional) wraps at ~72 chars and explains the **why**, not the what —
  the diff already shows the what.

We don't squash on merge; keep your commit history tidy on the branch (rebase
locally if you need to).

## Pull request flow

1. Fork & branch from `main`. Pick a short, kebab-case branch name
   (`feat/full-text-search`, `fix/login-rate-limit`).
2. Make your change. Run `make lint && make test` until green.
3. Open the PR against `main`. Fill in the
   [pull request template](.github/PULL_REQUEST_TEMPLATE.md) — what changed,
   why, how you tested.
4. CI runs Backend / Frontend / E2E workflows automatically. A green CI is a
   prerequisite for review.
5. Address review comments by pushing additional commits (don't force-push
   over feedback in flight). Once approved, a maintainer will merge.

## Reporting bugs / security

- **Bugs**: open a GitHub issue with the minimum repro (URL, steps, expected
  vs. actual, browser / PHP version, logs). Stack traces help.
- **Security**: please **don't** file a public issue. Open a
  [private security advisory](https://github.com/marekskopal/ukolio/security/advisories/new)
  on GitHub and we'll coordinate a fix and disclosure timeline.

## License

By contributing you agree that your contributions are licensed under the
project's [MIT License](LICENSE).
