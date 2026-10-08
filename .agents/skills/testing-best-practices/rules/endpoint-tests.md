# Endpoint Tests in IdentityService

## Exercise Both Authentication Boundaries

Client credentials authenticate the caller; JWT authentication identifies the user.
Test missing, invalid and deactivated client credentials separately from missing,
invalid, expired, revoked and cross-client user tokens when relevant.
Preserve AuthenticateClient before auth:id-api.

Refresh accepts the refresh token and client credentials without requiring an active
access token. Do not add access-token authentication merely to simplify test setup.

## Assert the Existing Contract

Choose applicable cases: success, capability rejection, invalid input, ownership,
client isolation and high-value security failures. Assert response plus relevant
state and outward effects. Trace the guard and repository checks rather than requiring
a policy for every endpoint.

Cross-client access must be refused using the endpoint's established status/code.
Do not replace authentication 401 responses with a universal 403 or 404 convention.
Session lookups must remain user-scoped and keep their domain error contract.

AuthException responses expose stable machine-readable codes. Laravel's standard
authentication failure may have no such code; preserve the distinction. Validation
tests normally assert 422 and field errors. Assert exact human text only when it is
a deliberate product contract. Malformed refresh input follows invalid-token 401.

## Put Matrices at the Layer That Owns Them

Test strategy normalization, validation and capability behavior at a suitable focused
layer, plus enough HTTP coverage to prove wiring and error rendering.
For a feature using policies, test the permission matrix there and HTTP enforcement.
For existing guard/ownership authorization, test that actual mechanism.

Use data providers for cases sharing setup. Avoid repeating an exhaustive matrix at
every layer, but retain coverage that detects missing wiring.

Consult installed-version Laravel/PHPUnit documentation for unfamiliar helpers.
Use named response assertions when available and existing project patterns otherwise.
