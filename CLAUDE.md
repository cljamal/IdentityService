# CLAUDE.md

This file provides guidance to coding agents working in this repository.
[AGENTS.md](AGENTS.md) is a pointer to this file. Keep important changes to project rules
and owner requirements here, not in AGENTS.md.

## What this project is

A Laravel 13 / PHP 8.3+ **stateless, multi-tenant JWT identity service**. It supports phone OTP,
email/password and username/password authentication, registration, password recovery and changes,
phone changes, account deletion, roles and token-based session tracking.

One instance/database serves independent clients. The same phone, email or username can identify
unrelated accounts under different clients. This is not SSO. Authentication uses the custom `id-api`
guard over `php-open-source-saver/jwt-auth`, not Laravel browser sessions or cookies.

Read [README.md](README.md) for setup and API behavior, `routes/api.php` for routes, and
`dev/IdentityService/` for the Bruno request collection. Setup examples in README.md do not grant
agents permission to execute migrations or create clients in a working database.

## Agent instructions and owner requirements

- Never create a commit without the owner's explicit request to commit. Permission to edit files is
  not permission to commit them.
- If a requirement is ambiguous or a decision is uncertain, ask the owner before deciding that point.
  Continue independent work that does not depend on the answer.
- Do not use PowerShell or CMD to edit files. Use `apply_patch`, Git Bash from
  `C:/Program Files/Git/bin`, or Linux tools through `IdentityServer`, preserving the existing encoding.
  Use readable edits; do not repeatedly submit opaque Base64 file-writing commands.
- Run PHP, Composer and Linux checks through the owner-provided `IdentityServer` alias:
  `IdentityServer ls`, `IdentityServer composer ...`, `IdentityServer php ...`.
  `GatewayServer` belongs to another project; do not use it for IdentityService.
- Verify the container project root with `IdentityServer pwd` and `IdentityServer ls` before making
  changes there. Never edit outside the IdentityService project root; do not guess its Linux path.
- If the alias is unavailable or additional tools need installing, tell the owner. Do not automatically
  install PHP/Composer on Windows or use a different project's container as a substitute.

These requirements adapt the general rules from Gateway's CLAUDE.md, which the owner gave higher
priority over conflicting older IdentityService rules. Gateway-specific proxy, Filament, Octane,
monitoring and deployment implementations are not requirements for this service.
Generated Boost guidelines must preserve the owner requirements in this file; they do not override them.

## Never run migrations

**Absolute rule.** Do not run `migrate`, `migrate:fresh`, `migrate:rollback`, `migrate:refresh`,
`db:wipe`, `db:seed` or anything else that changes database schema or data — on any environment,
including local and staging. Writing and editing migration files is fine; executing them is the
owner's call alone. When a change needs a migration run, say so and stop; do not run it, do not offer
to run it "just this once".

This restriction covers agent commands, manual SQL, Tinker and scripts, including indirect commands
such as `composer setup` (which runs `migrate --force` here) and `client:create`. Do not use real client
creation, token revocation, account deletion or other writes to a working database as tests.

The one unavoidable exception is the test suite: `RefreshDatabase` migrates the SQLite `:memory:`
database inside `artisan test`. Test fixtures and application writes confined to that database touch
nothing outside the test process. Anything beyond that is off limits.

## Working process and documentation

Mandatory per-edit logging is paused, following the owner's Gateway rules. Do not introduce or resume
mandatory recording of every file edit until the owner explicitly reinstates it.

This project currently has no PROGRESS.md or HANDOFF.md. Do not invent historical entries or copy
Gateway's architectural history. If a HANDOFF.md is introduced, keep it a curated, human-readable
companion for architecture rationale, outstanding work and style notes; update it when a whole
outstanding item is closed, not on every edit. Existing historical entries remain context.

Keep README.md, `.env.example` and the Bruno collection consistent with changes to documented
behavior, configuration or API contracts.

## Code and communication style

Code comments are in English. Keep them short and factual — explain a non-obvious constraint or a
decision that cost debugging time, not what the code already says. No narrative, no tutorials.
Human-facing strings stay Russian. Preserve machine-readable API error codes and protocol identifiers;
do not replace them with translated sentences.

Single-purpose application operations use `lorisleiva/laravel-actions` classes (`use AsAction;` +
`handle()`, called via `::run()`), following `app/Actions/Auth/`. Preserve the existing strategy,
repository and support-class responsibilities rather than turning every reusable collaborator into
an action.

## IdentityService architecture and invariants

### Client isolation and middleware order

- `AuthenticateClient` resolves an active client using `X-Client-Id` / `X-Client-Secret` and populates
  `CurrentClient`. These credentials authenticate the calling gateway, not the end user.
- On routes using both `client` and `auth:id-api`, client authentication must run first.
  `bootstrap/app.php` explicitly puts `AuthenticateClient` before `AuthenticatesRequests`; without
  this, the guard can cache a user before checking the current client. Preserve this ordering.
- Keep identity lookups, uniqueness, OTP subjects, session access and recovery operations isolated by
  client. A token or identifier from one client must not authorize operations under another.
- `IdApiGuard` rejects revoked sessions, users of deactivated clients and, where a client is resolved,
  access tokens issued for another client. Do not trust only the JWT's client claim for deactivation.
- `CurrentClient` is currently a singleton under the documented one-request-per-process assumption.
  Do not copy Gateway's Octane runtime settings into this app. A move to persistent workers requires
  reviewing request state and bindings so client/user state cannot leak between requests.

Read `app/Http/Middleware/AuthenticateClient.php`, `app/Auth/CurrentClient.php`,
`app/Auth/Guards/IdApiGuard.php` and the client isolation/middleware tests before changing this flow.

### Tokens, sessions and errors

- Successful authentication issues a short-lived JWT access token and an opaque refresh token.
  Refresh rotates the same session and its refresh token; it does not create a new login session.
- Stored refresh tokens are hashes. Reuse of a previous refresh token revokes the session.
  Losing a concurrent conditional rotation returns an invalid-token error without revoking the
  winning session. Preserve this distinction and the atomic update in `EloquentAuthSessionRepository`.
- `POST /api/auth/refresh` uses the refresh token in the body and client credentials, without requiring
  a still-valid access token. Do not add `auth:id-api` to this route.
- `AuthException` subclasses render stable `code` values and HTTP statuses centrally. Keep controllers
  delegating to actions rather than duplicating exception rendering at each call site.
  Client authentication failure and user authentication failure can both return 401; their codes
  distinguish them. Preserve that contract.
- `/api/auth/me` must refuse production requests. Keep the environment check in the controller so
  route caching cannot freeze the decision at build time.

### Strategies, storage and broadcasting

- Authentication strategies and their capability contracts live in `app/Auth/Strategies/`.
  Preserve independently configurable providers and existing normalization and recovery rules.
  Username has no delivery channel of its own; use the configured rescue resolver chain.
- Depend on existing repository interfaces where operations already use them. Bindings live in
  `AppServiceProvider`; do not bypass their client-scoping behavior with unscoped queries.
- Redis backs OTP state with expiration and resend cooldown. Those TTLs are deliberate live-state
  limits. Gateway's no-TTL configuration cache cascade is not implemented here and must not be
  imposed on OTP or token lifetimes.
- Preserve the separate broadcasting flows: `/api/broadcasting/auth` authenticates via `id-api`,
  while `/api/broadcasting/client-auth` uses client credentials. Keep API broadcasting stateless and
  preserve the client channel authorization checks in `ClientChannelAuthorizer`.

## Commands and verification

Run commands from the verified IdentityService root through the alias:

```sh
IdentityServer php artisan test --filter=MyTest
IdentityServer composer test
IdentityServer composer analyze
IdentityServer composer pint
IdentityServer npm run build
```

`composer analyze` runs PHPStan/Larastan at level 6; `composer pint` runs Laravel Pint.
Run checks appropriate to the change and report what actually ran and any limitations.

Tests use SQLite `:memory:` and array cache/session drivers (`phpunit.xml`). Verify effective test
configuration before running tests; a configuration cache must not redirect them to a working database.
Array cache configuration does not isolate direct `Redis` facade calls: OTP tests must bind
`OtpRepositoryInterface` to `tests/Fakes/FakeOtpRepository.php` or use a separate disposable test Redis.
Never point integration tests at the application's working Redis.

`tests/Concerns/ActsAsClient.php` must be declared after `RefreshDatabase` in a test's trait list so its
client fixture is created after the tables exist. Preserve client isolation, deactivation, session,
middleware priority and broadcasting regression coverage when changing the corresponding behavior.
Distinguish fake-backed tests from verified real Redis, delivery and deployment behavior.

## Known limits

SMS delivery currently uses a logging notifier; the email notifier binding is also a logging implementation.
Local OTP generation uses `1111`. Do not claim real delivery is configured or extend this shortcut to production.
There is no client administration API or configured CI pipeline documented here. Do not copy Gateway's
deployment commands or claim its infrastructure exists for IdentityService.

## Laravel Boost preparation

Before making application changes, verify PHP and Composer in the IdentityService container:

```sh
IdentityServer php -v
IdentityServer composer -V
```

If either is unavailable, tell the owner which prerequisite is missing and wait for the environment
to be prepared. Do not run the former automatic php.new installation commands on the host.

Install Laravel Boost as a development dependency from the verified application root if it is not
already installed, then generate the application-specific guidelines:

```sh
IdentityServer composer require laravel/boost --dev
IdentityServer php artisan boost:install
```

After installation, read both AGENTS.md and CLAUDE.md again and continue with the original request.
Keep AGENTS.md pointing to this file and retain owner requirements here if the generator changes the
instruction files. Incorporate the generated application-specific guidance without losing these rules.
Documentation-only changes do not require changing Composer dependencies.

The Codex MCP connection is registered as `identity-boost` on the Windows host, using
`D:/VMs/BinBoxes/IdentityServerMcp.bat`. This alias runs Boost over SSH without a terminal and
forwards stdin into the container. Keep it separate from the interactive `IdentityServer` alias.
The verified container root is `/var/www/TG/IdentityService`.

Generate or refresh guidelines with `IdentityServer php artisan boost:install --guidelines --no-interaction`.
`config/boost.php` directs Codex guidelines to CLAUDE.md, leaving AGENTS.md as a pointer.
MCP transport is configured on the Windows host; do not replace it with a container-local PHP command
generated by the MCP installer.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
