# Test Performance

Measure a slow test or suite before optimizing. Preserve isolation and coverage.
Use the smallest relevant selection during development:

```sh
IdentityServer php artisan test --filter=RelevantTest
```

Follow phpunit.xml's existing SQLite :memory:, array cache/session, low hashing cost
and disabled monitoring integrations. Do not change DB_URL or connection settings
to a working database for speed.

Keep RefreshDatabase unless a measured change preserves setup and migration semantics.
Configuration/route caching helpers are optional and need proof they preserve the
effective testing environment, client middleware and runtime environment checks.
Do not run deployment cache commands as routine test setup.

Fake slow external boundaries when the test does not own their behavior. Shared setup
fakes are allowed when consistently scoped; do not impose global Event::fake() or
other blanket fakes that hide role assignment or delivery coverage. Prevent stray
HTTP calls for outbound code, without inventing a new outbound dependency.

Use controlled time rather than sleep. Reduce redundant matrix coverage without
removing distinct contracts. Parallel testing is optional; do not install ParaTest
or change database isolation automatically. Additional tooling requires the owner.
