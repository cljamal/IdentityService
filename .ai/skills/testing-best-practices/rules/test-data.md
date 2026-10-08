# Test Data in IdentityService

## Own Fixtures Within Each Test Lifecycle

Use existing factories and explicit values for attributes affecting the outcome.
Create scenario-specific records in the test. Shared per-test setup may create the
minimum fixtures needed by every case; ActsAsClient is the existing example.
These fixtures are recreated for each test and are not shared mutable suite state.

Follow RefreshDatabase and ActsAsClient lifecycle conventions. Do not remove required
client setup merely to make each test method construct everything itself.

## Express the Security Boundary

For client isolation, create distinct clients and accounts with deliberately matching
identifiers where relevant. Check that lookups, uniqueness, OTP subjects and session
access remain scoped. For token rotation, distinguish current, previous, malformed
and concurrently replaced refresh tokens.

Use factories to produce realistic records rather than copying large fixture dumps.
Use make() for nonpersistent objects and create() only inside the approved test
database. Never create real clients or tokens as manual verification.

## Reuse Values Without Hiding the Scenario

Use PHPUnit data providers for input variations sharing setup and assertions.
Keep the reason for each case visible. Avoid factories or helpers that silently
grant roles, bypass client middleware or suppress events needed by the behavior.
Do not derive expected values from the implementation being tested.
