# Test Review

Check the applicable items; project rules take precedence over generic preferences.

- The test detects a distinct behavioral or security defect and survives an equivalent implementation.
- Relevant success and failure contracts are covered without mirroring every declaration.
- Client credentials, user authentication, client isolation and ownership are checked where applicable.
- Validation is tested through the actual Action/strategy boundary.
- Responses, stable error codes, persisted state and relevant events are asserted.
- Failure assertions reflect the contract: refresh reuse revokes, while a losing rotation preserves the winner.
- Event fakes do not suppress required synchronous role assignment or listeners under test.
- Fixtures and fakes are isolated per test; shared setup such as ActsAsClient is intentional.
- Time overrides and manually installed global state are restored.
- Database writes stay inside SQLite :memory: under the existing test configuration.
- Names and placement follow behavior groups already in the suite.
- No dependency installs, broad application rewrites or working-database operations were introduced as test setup.
- The smallest relevant test selection passed through IdentityServer, or a concrete limitation was reported.

Do not report a convention-following test as defective merely because it uses
Tests\TestCase in Unit, Carbon::setTestNow(), setup fixtures or repository mocks.
