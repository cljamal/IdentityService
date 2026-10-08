# Isolation in IdentityService

## Fake the Boundary the Test Does Not Own

Use real collaborators when their behavior is the subject. Use FakeOtpRepository for
isolated OTP storage tests and contract mocks for focused component tests. Mockery
is appropriate for repository contracts even when they are not remote services.
Do not mock every internal call or assert incidental implementation order.

Use selective Event::fake([...]) for the events under observation. Broad event fakes
can disable AssignDefaultRole and invalidate JWT role assertions. Run real listeners
when their invariants or delivery fan-out are under test. Fake delivery or transport
without suppressing the business behavior being checked.

Install fakes in the test when specific to that scenario. Shared per-test setup is
allowed for a consistent suite boundary, with framework lifecycle cleanup. Do not
globally fake events, notifications or queues without checking affected coverage.
For code using outbound HTTP, fake expected requests and prevent unintended network
calls when appropriate.

## Keep Database Tests Inside the Approved Test Database

Follow phpunit.xml: SQLite :memory:, empty DB_URL and array cache/session stores.
Feature tests use RefreshDatabase. ActsAsClient creates per-test client credentials,
default headers and CurrentClient for direct calls; preserve its lifecycle and the
nearby trait setup conventions.

Do not replace RefreshDatabase with LazilyRefreshDatabase as a blanket optimization.
Do not run tests against the working PostgreSQL database or execute migration/seed
commands separately. CLAUDE.md permits writes only in the isolated test database.
Database-specific behavior not reproducible in SQLite needs an agreed isolated
environment; it does not authorize using the working database.

## Control Time and Restore State

Use Laravel travel helpers or Carbon::setTestNow() consistently with nearby tests.
Carbon's global test clock is allowed when restored in tearDown or a finally block.
Reset singleton state and manually installed bindings when framework cleanup does
not cover them. Never use real sleep to test OTP expiry or cooldown.

Use deterministic inputs for token, cooldown and race tests where practical.
Do not rely on live Redis, SMS/email providers or another test's state.
